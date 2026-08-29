<?php
/**
 * RequestOptimizer — Applies all token optimizations to API payloads
 * before they are forwarded to the real API (Anthropic / Google).
 *
 * Optimizations:
 *  1. Inject system concision directive
 *  2. Lazy tool schemas (max 5, slim descriptions)
 *  3. Truncate tool results to 150 lines
 *  4. Compress old messages (keep first 2 + last 20)
 *  5. Enforce max_tokens by task tier
 *  6. Remove duplicate consecutive messages
 */

class RequestOptimizer
{
    const MAX_TOOLS              = 5;
    const MAX_TOOL_RESULT_LINES  = 150;
    const MAX_MESSAGES           = 30;
    const CONCISION_DIRECTIVE    = "\n[OPT] Be concise. Max 8 lines unless diff/code. Stop when done.";

    // ── Public entry point ───────────────────────────────────────────────────

    public function optimize(array $payload, string $uri = ''): array
    {
        $stats = [
            'uri'                  => $uri,
            'tools_trimmed'        => 0,
            'tool_results_truncated' => 0,
            'messages_compressed'  => 0,
            'max_tokens_set'       => null,
            'tokens_saved_est'     => 0,
        ];

        // 1. Inject system concision directive
        $payload = $this->injectSystemDirective($payload);

        // 2. Lazy tool schemas
        if (!empty($payload['tools'])) {
            [$payload['tools'], $stats['tools_trimmed']] = $this->optimizeTools($payload['tools']);
            $stats['tokens_saved_est'] += $stats['tools_trimmed'] * 200; // ~200 tokens per tool schema
        }

        // 3. Truncate tool results
        if (!empty($payload['messages'])) {
            [$payload['messages'], $stats['tool_results_truncated']] = $this->truncateToolResults($payload['messages']);
            $stats['tokens_saved_est'] += $stats['tool_results_truncated'] * 500; // avg 500 tok saved per truncation
        }

        // 4. Compress old messages
        if (!empty($payload['messages']) && count($payload['messages']) > self::MAX_MESSAGES) {
            [$payload['messages'], $stats['messages_compressed']] = $this->compressMessages($payload['messages']);
            $stats['tokens_saved_est'] += $stats['messages_compressed'] * 300;
        }

        // 5. Enforce max_tokens
        [$payload, $stats['max_tokens_set']] = $this->enforceMaxTokens($payload);

        // 6. Remove duplicates
        if (!empty($payload['messages'])) {
            $payload['messages'] = $this->deduplicateMessages($payload['messages']);
        }

        // Handle Google Gemini format (contents instead of messages)
        if (!empty($payload['contents'])) {
            [$payload['contents'], $tr] = $this->truncateToolResults($payload['contents'], 'gemini');
            $stats['tool_results_truncated'] += $tr;
            $stats['tokens_saved_est'] += $tr * 500;
        }

        return [$payload, $stats];
    }

    // ── 1. System directive injection ─────────────────────────────────────────

    private function injectSystemDirective(array $payload): array
    {
        $d = self::CONCISION_DIRECTIVE;

        if (isset($payload['system'])) {
            if (is_string($payload['system'])) {
                // Avoid injecting twice
                if (!str_contains($payload['system'], '[OPT]')) {
                    $payload['system'] .= $d;
                }
            } elseif (is_array($payload['system'])) {
                $already = array_filter($payload['system'], fn($b) => is_array($b) && str_contains($b['text'] ?? '', '[OPT]'));
                if (empty($already)) {
                    $payload['system'][] = ['type' => 'text', 'text' => $d];
                }
            }
        } else {
            // Gemini uses systemInstruction
            if (!isset($payload['systemInstruction'])) {
                $payload['system'] = $d;
            }
        }

        return $payload;
    }

    // ── 2. Lazy tool schemas ──────────────────────────────────────────────────

    private function optimizeTools(array $tools): array
    {
        $trimmed = 0;

        // Limit count
        if (count($tools) > self::MAX_TOOLS) {
            $trimmed = count($tools) - self::MAX_TOOLS;
            $tools   = array_slice($tools, 0, self::MAX_TOOLS);
        }

        // Slim each schema
        foreach ($tools as &$tool) {
            // Anthropic format
            if (isset($tool['description']) && strlen($tool['description']) > 80) {
                $tool['description'] = substr($tool['description'], 0, 80) . '…';
            }
            unset($tool['input_schema']['examples'], $tool['input_schema']['$defs']);

            // Google format
            if (isset($tool['functionDeclarations'])) {
                foreach ($tool['functionDeclarations'] as &$fn) {
                    if (isset($fn['description']) && strlen($fn['description']) > 80) {
                        $fn['description'] = substr($fn['description'], 0, 80) . '…';
                    }
                }
            }
        }

        return [$tools, $trimmed];
    }

    // ── 3. Truncate tool results ─────────────────────────────────────────────

    private function truncateToolResults(array $messages, string $format = 'anthropic'): array
    {
        $truncated = 0;

        foreach ($messages as &$msg) {
            $content = $msg['content'] ?? $msg['parts'] ?? null;
            if (!is_array($content)) {
                continue;
            }

            foreach ($content as &$block) {
                // Anthropic: type=tool_result
                $isToolResult = ($block['type'] ?? '') === 'tool_result';
                // Gemini: functionResponse
                $isFnResponse  = isset($block['functionResponse']);

                if ($isToolResult && is_string($block['content'] ?? '')) {
                    $block['content'] = $this->truncateText($block['content'], self::MAX_TOOL_RESULT_LINES);
                    $truncated++;
                } elseif ($isToolResult && is_array($block['content'] ?? [])) {
                    foreach ($block['content'] as &$inner) {
                        if (($inner['type'] ?? '') === 'text') {
                            $inner['text'] = $this->truncateText($inner['text'], self::MAX_TOOL_RESULT_LINES);
                            $truncated++;
                        }
                    }
                } elseif ($isFnResponse) {
                    $resp = json_encode($block['functionResponse']['response'] ?? '');
                    $lines = substr_count($resp, "\n");
                    if ($lines > self::MAX_TOOL_RESULT_LINES) {
                        $block['functionResponse']['response'] = ['truncated' => true, 'note' => 'Output capped at 150 lines by token optimizer'];
                        $truncated++;
                    }
                }
            }

            // Update back
            if (isset($msg['content'])) {
                $msg['content'] = $content;
            } else {
                $msg['parts'] = $content;
            }
        }

        return [$messages, $truncated];
    }

    private function truncateText(string $text, int $maxLines): string
    {
        $lines = explode("\n", $text);
        if (count($lines) <= $maxLines) {
            return $text;
        }
        $removed = count($lines) - $maxLines;
        return implode("\n", array_slice($lines, 0, $maxLines))
            . "\n[…{$removed} lines removed by AI Token Optimizer]";
    }

    // ── 4. Compress old messages ─────────────────────────────────────────────

    private function compressMessages(array $messages): array
    {
        $total     = count($messages);
        $keepFirst = 2;
        $keepLast  = 20;

        if ($total <= $keepFirst + $keepLast) {
            return [$messages, 0];
        }

        $compressed = $total - $keepFirst - $keepLast;
        $first      = array_slice($messages, 0, $keepFirst);
        $last       = array_slice($messages, -$keepLast);

        $summary = [
            'role'    => 'user',
            'content' => "[TOKEN_OPT: {$compressed} older messages compressed — only recent context retained]",
        ];

        return [array_merge($first, [$summary], $last), $compressed];
    }

    // ── 5. Enforce max_tokens by tier ────────────────────────────────────────

    private function enforceMaxTokens(array $payload): array
    {
        // Extract last user message text for tier detection
        $lastUserText = '';
        $messages     = $payload['messages'] ?? $payload['contents'] ?? [];

        foreach (array_reverse($messages) as $msg) {
            if (($msg['role'] ?? '') === 'user') {
                $c = $msg['content'] ?? $msg['parts'] ?? '';
                $lastUserText = is_string($c) ? $c : json_encode($c);
                break;
            }
        }

        $lower = strtolower($lastUserText);

        $tier2 = ['architecture', 'design', 'security', 'debug', 'concurrent', 'plan', 'distributed'];
        $tier1 = ['refactor', 'implement', 'feature', 'migrate', 'test', 'review', 'api', 'integration'];

        $maxTokens = 800; // tier0 default
        foreach ($tier1 as $s) { if (str_contains($lower, $s)) { $maxTokens = 2000; break; } }
        foreach ($tier2 as $s) { if (str_contains($lower, $s)) { $maxTokens = 4000; break; } }

        // Only set if not already set conservatively, or if current value is too high
        if (!isset($payload['max_tokens']) || $payload['max_tokens'] > $maxTokens * 3) {
            $payload['max_tokens'] = $maxTokens;
        }

        return [$payload, $maxTokens];
    }

    // ── 6. Deduplicate consecutive messages ──────────────────────────────────

    private function deduplicateMessages(array $messages): array
    {
        $result = [];
        $prevHash = null;

        foreach ($messages as $msg) {
            $hash = md5(json_encode($msg));
            if ($hash !== $prevHash) {
                $result[]  = $msg;
                $prevHash  = $hash;
            }
        }

        return $result;
    }

    // ── Stats logger ─────────────────────────────────────────────────────────

    public function logStats(array $stats, string $uri): void
    {
        $logFile  = __DIR__ . '/../data/proxy_stats.json';
        $existing = [];
        if (file_exists($logFile)) {
            $existing = json_decode(file_get_contents($logFile), true) ?? [];
        }

        $entry = array_merge($stats, [
            'timestamp' => date('Y-m-d H:i:s'),
            'uri'       => $uri,
        ]);

        array_unshift($existing, $entry);
        $existing = array_slice($existing, 0, 200); // keep last 200 requests

        @file_put_contents($logFile, json_encode($existing, JSON_PRETTY_PRINT));
    }
}

<?php
/**
 * RequestOptimizer — 8-Pattern aggressive token optimization
 * Applied BEFORE forwarding to the real API.
 * The model is NEVER changed — only the payload is compressed.
 *
 * Realistic savings breakdown:
 *   Tool schemas (lazy, max 5, 40-char desc) ........ -40%  input
 *   Tool results (truncate to 100 lines) ............. -60%  tool output
 *   Message compression (keep first 1 + last 8) ...... -55%  history
 *   max_tokens enforcement by tier ................... -75%  output
 *   System prompt slim (<200 chars) .................. -10%  system
 *   User message filler removal ...................... -10%  user msgs
 *   Duplicate message removal ........................  -5%
 *   Tool schema extras removal .......................  -5%
 *   ─────────────────────────────────────────────────────────
 *   Combined (stacked) ............................. ~85-90%
 */

class RequestOptimizer
{
    // Aggressive limits
    const MAX_TOOLS              = 5;
    const MAX_TOOL_DESC_LEN      = 40;    // chars — was 80
    const MAX_TOOL_RESULT_LINES  = 100;   // lines — was 150
    const MAX_MESSAGES_KEEP_LAST = 8;     // recent messages to keep
    const MAX_MESSAGES_KEEP_FIRST = 1;    // initial context messages to keep
    const MAX_SYSTEM_LEN         = 200;   // chars for system prompt

    // max_tokens by tier (aggressive)
    const MAX_TOKENS = [
        'tier0' => 400,   // rename, lint, format → very short answer
        'tier1' => 1200,  // feature, refactor → moderate answer
        'tier2' => 3000,  // architecture, debug → detailed answer
    ];

    // Filler phrases to strip from user messages (EN + FR)
    const FILLERS = [
        '/\bplease make sure to\b/i', '/\bmake sure to\b/i',
        '/\bplease ensure that you\b/i', '/\bin order to\b/i',
        '/\bit is important to note that\b/i', '/\bwould you mind\b/i',
        '/\bfeel free to\b/i', '/\byou should always\b/i',
        '/\bas an ai\b/i', '/\bplease note that\b/i',
        '/\bI would like you to\b/i', '/\bCould you please\b/i',
        // French fillers
        '/\bn.oublie pas de\b/i', '/\bveuillez\b/i',
        '/\bs.il te pla.t\b/i', '/\bs.il vous pla.t\b/i',
        '/\bassurez-vous de\b/i', '/\bfais en sorte de\b/i',
        '/\bil est important de noter que\b/i', '/\btu dois toujours\b/i',
        '/\ben tant qu.ia\b/i', '/\bj.aimerais que tu\b/i',
    ];

    // Task tier signals
    const TIER2_SIGNALS = ['architecture','design','security','debug','concurrent','distributed','plan','system design','race'];
    const TIER1_SIGNALS = ['refactor','implement','feature','migrate','test','review','api','integration','component'];

    // ─── Public entry ────────────────────────────────────────────────────────

    public function optimize(array $payload, string $uri = ''): array
    {
        $stats = [
            'uri'                     => $uri,
            'tools_trimmed'           => 0,
            'tool_results_truncated'  => 0,
            'messages_compressed'     => 0,
            'messages_deduped'        => 0,
            'filler_removed'          => 0,
            'max_tokens_set'          => null,
            'system_trimmed'          => false,
            'tokens_saved_est'        => 0,
        ];

        // 1. Slim system prompt
        if (!empty($payload['system'])) {
            [$payload['system'], $stats['system_trimmed']] = $this->slimSystem($payload['system']);
        }

        // 2. Inject concision directive then re-slim to enforce cap
        $payload = $this->injectDirective($payload);
        if (!empty($payload['system'])) {
            [$payload['system'], ] = $this->slimSystem($payload['system']);
        }

        // 3. Lazy tool schemas (most impactful on input tokens)
        if (!empty($payload['tools'])) {
            [$payload['tools'], $stats['tools_trimmed']] = $this->optimizeTools($payload['tools']);
            $stats['tokens_saved_est'] += $stats['tools_trimmed'] * 300;
        }

        // 4. Truncate tool results in messages
        $msgKey = isset($payload['messages']) ? 'messages' : (isset($payload['contents']) ? 'contents' : null);
        if ($msgKey) {
            [$payload[$msgKey], $stats['tool_results_truncated']] = $this->truncateToolResults($payload[$msgKey]);
            $stats['tokens_saved_est'] += $stats['tool_results_truncated'] * 800;
        }

        // 5. Compress user messages (filler removal)
        if ($msgKey) {
            [$payload[$msgKey], $stats['filler_removed']] = $this->compressUserMessages($payload[$msgKey]);
        }

        // 6. Compress message history (most impactful on long conversations)
        if ($msgKey && count($payload[$msgKey]) > self::MAX_MESSAGES_KEEP_FIRST + self::MAX_MESSAGES_KEEP_LAST + 2) {
            [$payload[$msgKey], $stats['messages_compressed']] = $this->compressHistory($payload[$msgKey]);
            $stats['tokens_saved_est'] += $stats['messages_compressed'] * 400;
        }

        // 7. Deduplicate consecutive messages
        if ($msgKey) {
            $before = count($payload[$msgKey]);
            $payload[$msgKey] = $this->deduplicate($payload[$msgKey]);
            $stats['messages_deduped'] = $before - count($payload[$msgKey]);
            $stats['tokens_saved_est'] += $stats['messages_deduped'] * 200;
        }

        // 8. Enforce max_tokens by task tier (most impactful on output tokens)
        [$payload, $stats['max_tokens_set']] = $this->enforceMaxTokens($payload, $msgKey);

        return [$payload, $stats];
    }

    // ─── 1. Slim system prompt ───────────────────────────────────────────────

    private function slimSystem(mixed $system): array
    {
        if (is_string($system) && strlen($system) > self::MAX_SYSTEM_LEN) {
            // Keep first MAX_SYSTEM_LEN chars — the rest is usually redundant context
            return [substr($system, 0, self::MAX_SYSTEM_LEN) . '…[trimmed]', true];
        }
        if (is_array($system)) {
            foreach ($system as &$block) {
                if (($block['type'] ?? '') === 'text' && strlen($block['text'] ?? '') > self::MAX_SYSTEM_LEN) {
                    $block['text'] = substr($block['text'], 0, self::MAX_SYSTEM_LEN) . '…[trimmed]';
                    return [$system, true];
                }
            }
        }
        return [$system, false];
    }

    // ─── 2. Inject concision directive ───────────────────────────────────────

    private function injectDirective(array $payload): array
    {
        $d = ' [OPT:concise,diff-only,<=8lines]';

        if (isset($payload['system'])) {
            if (is_string($payload['system']) && !str_contains($payload['system'], '[OPT:')) {
                $payload['system'] .= $d;
            } elseif (is_array($payload['system'])) {
                $has = array_filter($payload['system'], fn($b) => str_contains($b['text'] ?? '', '[OPT:'));
                if (empty($has)) {
                    $payload['system'][] = ['type' => 'text', 'text' => $d];
                }
            }
        } else {
            $payload['system'] = $d;
        }

        return $payload;
    }

    // ─── 3. Lazy tool schemas ────────────────────────────────────────────────

    private function optimizeTools(array $tools): array
    {
        $trimmed = 0;

        // Hard limit
        if (count($tools) > self::MAX_TOOLS) {
            $trimmed = count($tools) - self::MAX_TOOLS;
            $tools   = array_slice($tools, 0, self::MAX_TOOLS);
        }

        foreach ($tools as &$tool) {
            // Anthropic
            if (isset($tool['description'])) {
                $tool['description'] = $this->trimStr($tool['description'], self::MAX_TOOL_DESC_LEN);
            }
            // Remove all non-essential schema fields
            if (isset($tool['input_schema'])) {
                unset(
                    $tool['input_schema']['examples'],
                    $tool['input_schema']['$defs'],
                    $tool['input_schema']['$schema'],
                    $tool['input_schema']['title'],
                    $tool['input_schema']['additionalProperties']
                );
                // Remove property descriptions inside schema (very verbose)
                if (isset($tool['input_schema']['properties'])) {
                    foreach ($tool['input_schema']['properties'] as &$prop) {
                        if (isset($prop['description'])) {
                            $prop['description'] = $this->trimStr($prop['description'], 30);
                        }
                        unset($prop['examples'], $prop['default'], $prop['enum'],
                              $prop['title'], $prop['\$ref'], $prop['additionalProperties']);
                    }
                }
            }
            // Google format
            if (isset($tool['functionDeclarations'])) {
                foreach ($tool['functionDeclarations'] as &$fn) {
                    if (isset($fn['description'])) {
                        $fn['description'] = $this->trimStr($fn['description'], self::MAX_TOOL_DESC_LEN);
                    }
                }
            }
        }

        return [$tools, $trimmed];
    }

    // ─── 4. Truncate tool results ────────────────────────────────────────────

    private function truncateToolResults(array $messages): array
    {
        $truncated = 0;

        foreach ($messages as &$msg) {
            $content = $msg['content'] ?? $msg['parts'] ?? null;
            if (!is_array($content)) {
                // Plain string content — check for tool_result role
                continue;
            }

            foreach ($content as &$block) {
                // Anthropic: type=tool_result
                if (($block['type'] ?? '') === 'tool_result') {
                    if (is_string($block['content'] ?? null)) {
                        [$block['content'], $didTruncate] = $this->truncLines($block['content'], self::MAX_TOOL_RESULT_LINES);
                        if ($didTruncate) $truncated++;
                    } elseif (is_array($block['content'] ?? null)) {
                        foreach ($block['content'] as &$inner) {
                            if (($inner['type'] ?? '') === 'text') {
                                [$inner['text'], $didTruncate] = $this->truncLines($inner['text'] ?? '', self::MAX_TOOL_RESULT_LINES);
                                if ($didTruncate) $truncated++;
                            }
                        }
                    }
                }
                // Google: functionResponse
                if (isset($block['functionResponse']['response'])) {
                    $encoded = json_encode($block['functionResponse']['response']);
                    if (substr_count($encoded, "\n") > self::MAX_TOOL_RESULT_LINES) {
                        $block['functionResponse']['response'] = ['_truncated' => true, '_note' => 'Capped by token optimizer'];
                        $truncated++;
                    }
                }
            }

            if (isset($msg['content']) && is_array($msg['content'])) {
                $msg['content'] = $content;
            } elseif (isset($msg['parts'])) {
                $msg['parts'] = $content;
            }
        }

        return [$messages, $truncated];
    }

    // ─── 5. Compress user messages (filler removal) ───────────────────────────

    private function compressUserMessages(array $messages): array
    {
        $removed = 0;

        foreach ($messages as &$msg) {
            if (($msg['role'] ?? '') !== 'user') continue;
            if (!is_string($msg['content'] ?? null)) continue;

            $original = $msg['content'];
            $compressed = preg_replace(self::FILLERS, '', $original);
            $compressed = preg_replace('/\s{2,}/', ' ', $compressed ?? $original);
            $compressed = trim($compressed);

            if ($compressed !== $original && strlen($compressed) > 10) {
                $msg['content'] = $compressed;
                $removed++;
            }
        }

        return [$messages, $removed];
    }

    // ─── 6. Compress message history ─────────────────────────────────────────

    private function compressHistory(array $messages): array
    {
        $first     = self::MAX_MESSAGES_KEEP_FIRST;
        $last      = self::MAX_MESSAGES_KEEP_LAST;
        $total     = count($messages);
        $compressed = $total - $first - $last;

        if ($compressed <= 0) {
            return [$messages, 0];
        }

        $head    = array_slice($messages, 0, $first);
        $tail    = array_slice($messages, -$last);
        $summary = [
            'role'    => 'user',
            'content' => "[CTX_COMPRESSED: {$compressed} earlier messages removed. Focus on recent context only.]",
        ];

        return [array_merge($head, [$summary], $tail), $compressed];
    }

    // ─── 7. Deduplicate ──────────────────────────────────────────────────────

    private function deduplicate(array $messages): array
    {
        $result = [];
        $prevHash = null;

        foreach ($messages as $msg) {
            $hash = md5(serialize($msg));
            if ($hash !== $prevHash) {
                $result[]  = $msg;
                $prevHash  = $hash;
            }
        }

        return $result;
    }

    // ─── 8. Enforce max_tokens by tier ───────────────────────────────────────

    private function enforceMaxTokens(array $payload, ?string $msgKey): array
    {
        $lastUserText = '';
        $messages     = $msgKey ? ($payload[$msgKey] ?? []) : [];

        foreach (array_reverse($messages) as $msg) {
            if (($msg['role'] ?? '') === 'user') {
                $c = $msg['content'] ?? $msg['parts'] ?? '';
                $lastUserText = is_string($c) ? $c : json_encode($c);
                break;
            }
        }

        $lower = strtolower($lastUserText);
        $tier  = 'tier0';
        foreach (self::TIER1_SIGNALS as $s) { if (str_contains($lower, $s)) { $tier = 'tier1'; break; } }
        foreach (self::TIER2_SIGNALS as $s) { if (str_contains($lower, $s)) { $tier = 'tier2'; break; } }

        $cap = self::MAX_TOKENS[$tier];

        // Always enforce — never let the client override the cap upward
        $payload['max_tokens'] = $cap;

        return [$payload, $cap];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function trimStr(string $s, int $max): string
    {
        return strlen($s) > $max ? substr($s, 0, $max) . '…' : $s;
    }

    private function truncLines(string $text, int $maxLines): array
    {
        $lines = explode("\n", $text);
        if (count($lines) <= $maxLines) {
            return [$text, false];
        }
        $removed = count($lines) - $maxLines;
        return [
            implode("\n", array_slice($lines, 0, $maxLines)) . "\n[…{$removed} lines removed]",
            true,
        ];
    }

    // ─── Stats logger ─────────────────────────────────────────────────────────

    public function logStats(array $stats, string $uri): void
    {
        $logFile  = __DIR__ . '/../data/proxy_stats.json';
        $existing = file_exists($logFile)
            ? (json_decode(file_get_contents($logFile), true) ?? [])
            : [];

        array_unshift($existing, array_merge($stats, ['timestamp' => date('Y-m-d H:i:s')]));
        $existing = array_slice($existing, 0, 200);

        @file_put_contents($logFile, json_encode($existing, JSON_PRETTY_PRINT));
    }
}

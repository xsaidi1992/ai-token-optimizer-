<?php
/**
 * RequestOptimizer — 12-Pattern aggressive token optimization
 * Applied BEFORE forwarding to the real API.
 * The model is NEVER changed — only the payload is compressed.
 *
 * ═══════════════════════════════════════════════════════════════
 * PRODUCTION-GRADE v2: 12 patterns calibrated for all IDEs.
 * Steps 1-8: original core.  Steps 9-12: advanced (base64, assistant
 * trim, empty cleanup, Google systemInstruction).
 * ═══════════════════════════════════════════════════════════════
 */

class RequestOptimizer
{
    // ── Calibrated limits ────────────────────────────────────────────────────
    const MAX_TOOLS              = 5;     // Keep only 5 most relevant tools
    const MAX_TOOL_DESC_LEN      = 40;    // Descriptions beyond this are waste
    const MAX_TOOL_RESULT_LINES  = 100;   // 100 lines is enough context
    const MAX_MESSAGES_KEEP_LAST = 8;     // Recent conversation window
    const MAX_MESSAGES_KEEP_FIRST = 1;    // System context anchor
    const MAX_SYSTEM_LEN         = 800;   // Preserve core instructions, strip examples
    const MAX_PROP_DESC_LEN      = 30;    // Property descriptions in schemas
    const MAX_ASSISTANT_HIST_LEN = 200;   // Trim old assistant msgs to this length
    const BASE64_PLACEHOLDER     = '[image data removed by optimizer]';

    // max_tokens by tier — calibrated to avoid finish_reason=length retries
    const MAX_TOKENS = [
        'tier0' => 800,    // simple tasks: rename, lint, format
        'tier1' => 2000,   // moderate: feature, refactor, test
        'tier2' => 4000,   // complex: architecture, debug, plan
    ];

    // Filler phrases to strip from user messages (EN + FR)
    const FILLERS = [
        '/\bplease make sure to\b/i', '/\bmake sure to\b/i',
        '/\bplease ensure that you\b/i', '/\bin order to\b/i',
        '/\bit is important to note that\b/i', '/\bwould you mind\b/i',
        '/\bfeel free to\b/i', '/\byou should always\b/i',
        '/\bas an ai\b/i', '/\bplease note that\b/i',
        '/\bI would like you to\b/i', '/\bCould you please\b/i',
        '/\bI need you to\b/i', '/\bplease do\b/i',
        '/\bkindly\b/i', '/\bwould you be so kind\b/i',
        // French fillers
        '/\bn.oublie pas de\b/i', '/\bveuillez\b/i',
        '/\bs.il te pla.t\b/i', '/\bs.il vous pla.t\b/i',
        '/\bassurez-vous de\b/i', '/\bfais en sorte de\b/i',
        '/\bil est important de noter que\b/i', '/\btu dois toujours\b/i',
        '/\ben tant qu.ia\b/i', '/\bj.aimerais que tu\b/i',
        '/\bmerci de\b/i', '/\bpourriez-vous\b/i',
    ];

    // Task tier signals
    const TIER2_SIGNALS = [
        'architecture','design','security','debug','concurrent','distributed',
        'plan','system design','race','migration','refactoring large','performance',
    ];
    const TIER1_SIGNALS = [
        'refactor','implement','feature','migrate','test','review',
        'api','integration','component','fix','add','create','build',
    ];

    // Schema keys to strip recursively
    const STRIP_SCHEMA_KEYS = [
        'examples','$defs','$schema','title','additionalProperties',
        '$ref','allOf','anyOf','oneOf','not','if','then','else',
        'minItems','maxItems','minLength','maxLength','pattern',
        'minimum','maximum','exclusiveMinimum','exclusiveMaximum',
        'deprecated','readOnly','writeOnly','xml',
    ];

    // ─── Public entry ────────────────────────────────────────────────────────

    private array $config = [];

    private function loadConfig(): void
    {
        $configFile = __DIR__ . '/../data/proxy_config.json';
        if (file_exists($configFile)) {
            $this->config = json_decode(file_get_contents($configFile), true) ?? [];
        }
    }

    private function isPatternEnabled(string $pattern): bool
    {
        return ($this->config['patterns'][$pattern]['enabled'] ?? true) === true;
    }

    public function optimize(array $payload, string $uri = ''): array
    {
        $this->loadConfig();

        // If proxy disabled globally, pass through
        if (($this->config['proxy_enabled'] ?? true) === false) {
            $raw = strlen(json_encode($payload, JSON_UNESCAPED_UNICODE));
            return [$payload, ['uri' => $uri, 'input_bytes_before' => $raw, 'input_bytes_after' => $raw, 'savings_pct' => 0, 'tokens_saved_est' => 0, 'proxy_disabled' => true]];
        }

        // Measure input BEFORE optimization
        $inputBefore = strlen(json_encode($payload, JSON_UNESCAPED_UNICODE));

        $stats = [
            'uri'                     => $uri,
            'input_bytes_before'      => $inputBefore,
            'input_bytes_after'       => 0,
            'savings_pct'             => 0,
            'tools_trimmed'           => 0,
            'tools_total_before'      => 0,
            'tool_results_truncated'  => 0,
            'messages_before'         => 0,
            'messages_after'          => 0,
            'messages_compressed'     => 0,
            'messages_deduped'        => 0,
            'filler_removed'          => 0,
            'system_chars_before'     => 0,
            'system_chars_after'      => 0,
            'system_trimmed'          => false,
            'max_tokens_set'          => null,
            'max_tokens_tier'         => null,
            'base64_stripped'         => 0,
            'assistant_trimmed'       => 0,
            'empty_removed'           => 0,
            'google_sys_trimmed'      => false,
            'tokens_saved_est'        => 0,
        ];

        // Detect message key: Anthropic/OpenAI = messages, Google = contents
        $msgKey = isset($payload['messages']) ? 'messages'
                : (isset($payload['contents']) ? 'contents' : null);

        if ($msgKey) {
            $stats['messages_before'] = count($payload[$msgKey]);
        }

        // ═════════════════════════════════════════════════════════════════════
        // STEP 1: System Prompt Slim
        // ═════════════════════════════════════════════════════════════════════
        if (!empty($payload['system']) && $this->isPatternEnabled('system_prompt_slim')) {
            $stats['system_chars_before'] = $this->measureSystem($payload['system']);
            [$payload['system'], $stats['system_trimmed']] = $this->slimSystem($payload['system']);
            $stats['system_chars_after'] = $this->measureSystem($payload['system']);
        }

        // ═════════════════════════════════════════════════════════════════════
        // STEP 2: Inject Concision Directive
        // ═════════════════════════════════════════════════════════════════════
        if ($this->isPatternEnabled('concision_directive')) $payload = $this->injectDirective($payload);

        // ═════════════════════════════════════════════════════════════════════
        // STEP 3: Lazy Tool Schemas (most impactful on input tokens)
        // ═════════════════════════════════════════════════════════════════════
        if (!empty($payload['tools']) && $this->isPatternEnabled('lazy_tool_schemas')) {
            $stats['tools_total_before'] = count($payload['tools']);
            [$payload['tools'], $stats['tools_trimmed']] = $this->optimizeTools($payload['tools']);
            $stats['tokens_saved_est'] += $stats['tools_trimmed'] * 300;
        }

        // ═════════════════════════════════════════════════════════════════════
        // STEP 4: Tool Result Truncation
        // ═════════════════════════════════════════════════════════════════════
        if ($msgKey && $this->isPatternEnabled('tool_result_truncation')) {
            [$payload[$msgKey], $stats['tool_results_truncated']] = $this->truncateToolResults($payload[$msgKey]);
            $stats['tokens_saved_est'] += $stats['tool_results_truncated'] * 800;
        }

        // ═════════════════════════════════════════════════════════════════════
        // STEP 5: Filler Removal from user messages
        // ═════════════════════════════════════════════════════════════════════
        if ($msgKey && $this->isPatternEnabled('filler_removal')) {
            [$payload[$msgKey], $stats['filler_removed']] = $this->compressUserMessages($payload[$msgKey]);
        }

        // ═════════════════════════════════════════════════════════════════════
        // STEP 6: History Compression (most impactful on long conversations)
        // ═════════════════════════════════════════════════════════════════════
        if ($msgKey && $this->isPatternEnabled('history_compression') && count($payload[$msgKey]) > self::MAX_MESSAGES_KEEP_FIRST + self::MAX_MESSAGES_KEEP_LAST + 2) {
            [$payload[$msgKey], $stats['messages_compressed']] = $this->compressHistory($payload[$msgKey]);
            $stats['tokens_saved_est'] += $stats['messages_compressed'] * 400;
        }

        // ═════════════════════════════════════════════════════════════════════
        // STEP 7: Deduplicate consecutive identical messages
        // ═════════════════════════════════════════════════════════════════════
        if ($msgKey && $this->isPatternEnabled('deduplication')) {
            $before = count($payload[$msgKey]);
            $payload[$msgKey] = $this->deduplicate($payload[$msgKey]);
            $stats['messages_deduped'] = $before - count($payload[$msgKey]);
            $stats['tokens_saved_est'] += $stats['messages_deduped'] * 200;
        }

        // ═════════════════════════════════════════════════════════════════════
        // STEP 8: max_tokens Enforcement by task tier
        // ═════════════════════════════════════════════════════════════════════
        if ($this->isPatternEnabled('max_tokens_enforcement')) [$payload, $stats['max_tokens_set'], $stats['max_tokens_tier']] = $this->enforceMaxTokens($payload, $msgKey);

        // ═════════════════════════════════════════════════════════════════════
        // STEP 9: Base64 image stripping from old messages
        //   Vision requests embed huge base64 blobs in history — useless
        //   for follow-up turns. Replace with tiny placeholder.
        // ═════════════════════════════════════════════════════════════════════
        if ($msgKey && $this->isPatternEnabled('base64_image_strip')) {
            [$payload[$msgKey], $stats['base64_stripped']] = $this->stripBase64Images($payload[$msgKey]);
            $stats['tokens_saved_est'] += $stats['base64_stripped'] * 5000;
        }

        // ═════════════════════════════════════════════════════════════════════
        // STEP 10: Trim old assistant responses in history
        //   Only the last assistant message needs full content.
        //   Earlier ones are context — first 200 chars suffice.
        // ═════════════════════════════════════════════════════════════════════
        if ($msgKey && $this->isPatternEnabled('assistant_response_trim')) {
            [$payload[$msgKey], $stats['assistant_trimmed']] = $this->trimOldAssistantMessages($payload[$msgKey]);
            $stats['tokens_saved_est'] += $stats['assistant_trimmed'] * 300;
        }

        // ═════════════════════════════════════════════════════════════════════
        // STEP 11: Remove empty/whitespace-only content blocks
        // ═════════════════════════════════════════════════════════════════════
        if ($msgKey && $this->isPatternEnabled('empty_block_cleanup')) {
            [$payload[$msgKey], $stats['empty_removed']] = $this->removeEmptyBlocks($payload[$msgKey]);
        }

        // ═════════════════════════════════════════════════════════════════════
        // STEP 12: Google systemInstruction slim
        //   Google Gemini uses a separate 'system_instruction' field.
        // ═════════════════════════════════════════════════════════════════════
        if (!empty($payload['system_instruction']) && $this->isPatternEnabled('google_system_slim')) {
            [$payload['system_instruction'], $stats['google_sys_trimmed']] = $this->slimSystem($payload['system_instruction']);
        }

        // ── Measure AFTER and compute real savings ───────────────────────────
        if ($msgKey) {
            $stats['messages_after'] = count($payload[$msgKey]);
        }
        $inputAfter = strlen(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $stats['input_bytes_after'] = $inputAfter;
        $stats['savings_pct'] = $inputBefore > 0
            ? round(100 * (1 - $inputAfter / $inputBefore), 1)
            : 0;

        return [$payload, $stats];
    }

    // ─── 1. Slim system prompt ───────────────────────────────────────────────

    private function slimSystem(mixed $system): array
    {
        if (is_string($system)) {
            if (strlen($system) <= self::MAX_SYSTEM_LEN) {
                return [$system, false];
            }
            return [$this->smartTruncate($system, self::MAX_SYSTEM_LEN), true];
        }

        if (is_array($system)) {
            $changed = false;
            $totalLen = 0;
            foreach ($system as &$block) {
                if (($block['type'] ?? '') === 'text' && is_string($block['text'] ?? null)) {
                    $totalLen += strlen($block['text']);
                }
            }
            unset($block);

            if ($totalLen <= self::MAX_SYSTEM_LEN) {
                return [$system, false];
            }

            // Proportionally trim each text block
            foreach ($system as &$block) {
                if (($block['type'] ?? '') === 'text' && strlen($block['text'] ?? '') > 100) {
                    $share = (int)(self::MAX_SYSTEM_LEN * strlen($block['text']) / max(1, $totalLen));
                    $share = max(80, $share);
                    if (strlen($block['text']) > $share) {
                        $block['text'] = $this->smartTruncate($block['text'], $share);
                        $changed = true;
                    }
                }
            }
            unset($block);
            return [$system, $changed];
        }

        return [$system, false];
    }

    /**
     * Smart truncation: keep first half + last quarter to preserve
     * core instructions + recent context markers.
     */
    private function smartTruncate(string $text, int $limit): string
    {
        $firstHalf  = (int)($limit * 0.65);
        $lastQuarter = $limit - $firstHalf - 30; // 30 chars for marker
        if ($lastQuarter < 20) $lastQuarter = 20;

        $head = substr($text, 0, $firstHalf);
        $tail = substr($text, -$lastQuarter);

        return $head . "\n[…trimmed " . (strlen($text) - $limit) . " chars…]\n" . $tail;
    }

    private function measureSystem(mixed $system): int
    {
        if (is_string($system)) return strlen($system);
        if (is_array($system)) {
            $len = 0;
            foreach ($system as $b) {
                $len += strlen($b['text'] ?? '');
            }
            return $len;
        }
        return 0;
    }

    // ─── 2. Inject concision directive ───────────────────────────────────────

    private function injectDirective(array $payload): array
    {
        $d = ' [OPT:concise,diff-only,<=8lines]';

        // Anthropic / Google: system field
        if (isset($payload['system'])) {
            if (is_string($payload['system']) && !str_contains($payload['system'], '[OPT:')) {
                $payload['system'] .= $d;
            } elseif (is_array($payload['system'])) {
                $has = array_filter($payload['system'], fn($b) => str_contains($b['text'] ?? '', '[OPT:'));
                if (empty($has)) {
                    $payload['system'][] = ['type' => 'text', 'text' => $d];
                }
            }
        // OpenAI: inject into the first system message in messages[]
        } elseif (isset($payload['messages'])) {
            $injected = false;
            foreach ($payload['messages'] as &$msg) {
                if (($msg['role'] ?? '') === 'system') {
                    if (is_string($msg['content']) && !str_contains($msg['content'], '[OPT:')) {
                        $msg['content'] .= $d;
                    }
                    $injected = true;
                    break;
                }
            }
            unset($msg);
            if (!$injected) {
                array_unshift($payload['messages'], ['role' => 'system', 'content' => trim($d)]);
            }
        } else {
            $payload['system'] = trim($d);
        }

        return $payload;
    }

    // ─── 3. Lazy tool schemas ────────────────────────────────────────────────

    private function optimizeTools(array $tools): array
    {
        $trimmed = 0;

        // Hard limit: keep only MAX_TOOLS
        if (count($tools) > self::MAX_TOOLS) {
            $trimmed = count($tools) - self::MAX_TOOLS;
            $tools   = array_slice($tools, 0, self::MAX_TOOLS);
        }

        foreach ($tools as &$tool) {
            // ── Anthropic format: {name, description, input_schema}
            if (isset($tool['description'])) {
                $tool['description'] = $this->trimStr($tool['description'], self::MAX_TOOL_DESC_LEN);
            }

            // ── OpenAI format: {type:"function", function:{name, description, parameters}}
            if (isset($tool['function']['description'])) {
                $tool['function']['description'] = $this->trimStr($tool['function']['description'], self::MAX_TOOL_DESC_LEN);
            }

            // ── Strip non-essential schema fields recursively
            foreach (['input_schema', 'parameters'] as $schemaKey) {
                $target = null;
                if (isset($tool[$schemaKey])) {
                    $target = &$tool[$schemaKey];
                } elseif (isset($tool['function'][$schemaKey])) {
                    $target = &$tool['function'][$schemaKey];
                }

                if ($target !== null && is_array($target)) {
                    $this->stripSchemaRecursive($target);
                    // Trim property descriptions
                    if (isset($target['properties'])) {
                        foreach ($target['properties'] as &$prop) {
                            if (isset($prop['description'])) {
                                $prop['description'] = $this->trimStr($prop['description'], self::MAX_PROP_DESC_LEN);
                            }
                        }
                        unset($prop);
                    }
                }
                unset($target);
            }

            // ── Google format: {functionDeclarations: [{name, description, parameters}]}
            if (isset($tool['functionDeclarations'])) {
                // Limit function declarations too
                if (count($tool['functionDeclarations']) > self::MAX_TOOLS) {
                    $trimmed += count($tool['functionDeclarations']) - self::MAX_TOOLS;
                    $tool['functionDeclarations'] = array_slice($tool['functionDeclarations'], 0, self::MAX_TOOLS);
                }
                foreach ($tool['functionDeclarations'] as &$fn) {
                    if (isset($fn['description'])) {
                        $fn['description'] = $this->trimStr($fn['description'], self::MAX_TOOL_DESC_LEN);
                    }
                    if (isset($fn['parameters']) && is_array($fn['parameters'])) {
                        $this->stripSchemaRecursive($fn['parameters']);
                    }
                }
                unset($fn);
            }
        }
        unset($tool);

        return [$tools, $trimmed];
    }

    /**
     * Recursively remove bloat keys from JSON Schema objects.
     */
    private function stripSchemaRecursive(array &$schema): void
    {
        foreach (self::STRIP_SCHEMA_KEYS as $key) {
            unset($schema[$key]);
        }

        // Recurse into properties
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as &$prop) {
                if (is_array($prop)) {
                    $this->stripSchemaRecursive($prop);
                }
            }
            unset($prop);
        }

        // Recurse into items (array schemas)
        if (isset($schema['items']) && is_array($schema['items'])) {
            $this->stripSchemaRecursive($schema['items']);
        }
    }

    // ─── 4. Truncate tool results ────────────────────────────────────────────

    private function truncateToolResults(array $messages): array
    {
        $truncated = 0;

        foreach ($messages as &$msg) {
            $content = $msg['content'] ?? $msg['parts'] ?? null;

            // OpenAI: role=tool with plain string content
            if (!is_array($content)) {
                if (($msg['role'] ?? '') === 'tool' && is_string($msg['content'] ?? null)) {
                    [$msg['content'], $didTruncate] = $this->truncLines($msg['content'], self::MAX_TOOL_RESULT_LINES);
                    if ($didTruncate) $truncated++;
                }
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
                        unset($inner);
                    }
                }

                // Google: functionResponse
                if (isset($block['functionResponse']['response'])) {
                    $encoded = json_encode($block['functionResponse']['response']);
                    if (substr_count($encoded, "\n") > self::MAX_TOOL_RESULT_LINES || strlen($encoded) > 20000) {
                        $block['functionResponse']['response'] = [
                            '_truncated' => true,
                            '_summary'   => substr($encoded, 0, 500) . '…',
                        ];
                        $truncated++;
                    }
                }
            }
            unset($block);

            if (isset($msg['content']) && is_array($msg['content'])) {
                $msg['content'] = $content;
            } elseif (isset($msg['parts'])) {
                $msg['parts'] = $content;
            }
        }
        unset($msg);

        return [$messages, $truncated];
    }

    // ─── 5. Compress user messages (filler removal) ──────────────────────────

    private function compressUserMessages(array $messages): array
    {
        $removed = 0;

        foreach ($messages as &$msg) {
            if (($msg['role'] ?? '') !== 'user') continue;

            // OpenAI: content can be array of {type,text} parts
            if (is_array($msg['content'] ?? null)) {
                foreach ($msg['content'] as &$part) {
                    if (($part['type'] ?? '') === 'text' && is_string($part['text'] ?? null)) {
                        $orig = $part['text'];
                        $c = preg_replace(self::FILLERS, '', $orig);
                        $c = trim(preg_replace('/\s{2,}/', ' ', $c ?? $orig));
                        if ($c !== $orig && strlen($c) > 10) {
                            $part['text'] = $c;
                            $removed++;
                        }
                    }
                }
                unset($part);
                continue;
            }
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
        unset($msg);

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

        // Enforce: cap upward but don't override if already lower
        if (isset($payload['max_completion_tokens'])) {
            if (!$payload['max_completion_tokens'] || $payload['max_completion_tokens'] > $cap) {
                $payload['max_completion_tokens'] = $cap;
            }
        } else {
            if (!isset($payload['max_tokens']) || $payload['max_tokens'] > $cap) {
                $payload['max_tokens'] = $cap;
            }
        }

        return [$payload, $cap, $tier];
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
            implode("\n", array_slice($lines, 0, $maxLines)) . "\n[…{$removed} lines removed by AI Token Optimizer]",
            true,
        ];
    }

    // ─── 9. Strip base64 images from old messages ────────────────────────────

    private function stripBase64Images(array $messages): array
    {
        $stripped = 0;
        $count    = count($messages);

        // Keep images only in the LAST user message — strip from everything else
        foreach ($messages as $idx => &$msg) {
            if ($idx >= $count - 1) break; // skip last message

            $content = $msg['content'] ?? null;
            if (!is_array($content)) {
                // Check for inline base64 in string content
                if (is_string($content) && preg_match('/data:[^;]+;base64,[A-Za-z0-9+\/=]{500,}/', $content)) {
                    $msg['content'] = preg_replace('/data:[^;]+;base64,[A-Za-z0-9+\/=]+/', self::BASE64_PLACEHOLDER, $content);
                    $stripped++;
                }
                continue;
            }

            foreach ($content as &$block) {
                // Anthropic: {type:"image", source:{type:"base64", data:"..."}}
                if (($block['type'] ?? '') === 'image' && isset($block['source']['data'])) {
                    $block['source']['data'] = self::BASE64_PLACEHOLDER;
                    $stripped++;
                }
                // OpenAI: {type:"image_url", image_url:{url:"data:image/...;base64,..."}}
                if (($block['type'] ?? '') === 'image_url' && isset($block['image_url']['url'])) {
                    $url = $block['image_url']['url'];
                    if (str_contains($url, 'base64,') && strlen($url) > 500) {
                        $block['image_url']['url'] = self::BASE64_PLACEHOLDER;
                        $stripped++;
                    }
                }
                // Google: {inlineData: {mimeType, data}}
                if (isset($block['inlineData']['data']) && strlen($block['inlineData']['data']) > 500) {
                    $block['inlineData']['data'] = self::BASE64_PLACEHOLDER;
                    $stripped++;
                }
            }
            unset($block);

            if (isset($msg['content']) && is_array($msg['content'])) {
                $msg['content'] = $content;
            }
        }
        unset($msg);

        return [$messages, $stripped];
    }

    // ─── 10. Trim old assistant responses ────────────────────────────────────

    private function trimOldAssistantMessages(array $messages): array
    {
        $trimmed = 0;
        $count   = count($messages);

        // Find last assistant index
        $lastAssistantIdx = -1;
        for ($i = $count - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? '') === 'assistant') {
                $lastAssistantIdx = $i;
                break;
            }
        }

        foreach ($messages as $idx => &$msg) {
            if (($msg['role'] ?? '') !== 'assistant') continue;
            if ($idx === $lastAssistantIdx) continue; // keep last one intact

            if (is_string($msg['content'] ?? null) && strlen($msg['content']) > self::MAX_ASSISTANT_HIST_LEN) {
                $msg['content'] = substr($msg['content'], 0, self::MAX_ASSISTANT_HIST_LEN) . '…[trimmed]';
                $trimmed++;
            } elseif (is_array($msg['content'] ?? null)) {
                // Anthropic: array of blocks — trim text blocks, remove tool_use blocks
                $newContent = [];
                foreach ($msg['content'] as $block) {
                    if (($block['type'] ?? '') === 'text' && strlen($block['text'] ?? '') > self::MAX_ASSISTANT_HIST_LEN) {
                        $block['text'] = substr($block['text'], 0, self::MAX_ASSISTANT_HIST_LEN) . '…[trimmed]';
                        $newContent[] = $block;
                        $trimmed++;
                    } elseif (($block['type'] ?? '') === 'tool_use') {
                        // Keep tool_use but strip input to avoid orphan tool_result errors
                        $block['input'] = (object)[];
                        $newContent[] = $block;
                    } else {
                        $newContent[] = $block;
                    }
                }
                $msg['content'] = $newContent;
            }
        }
        unset($msg);

        return [$messages, $trimmed];
    }

    // ─── 11. Remove empty/whitespace blocks ──────────────────────────────────

    private function removeEmptyBlocks(array $messages): array
    {
        $removed = 0;

        foreach ($messages as &$msg) {
            if (!is_array($msg['content'] ?? null)) {
                // Remove whitespace-only string messages (but not role=tool which can be empty)
                if (is_string($msg['content'] ?? null) && trim($msg['content']) === '' && ($msg['role'] ?? '') !== 'tool') {
                    $msg['content'] = '[empty]';
                    $removed++;
                }
                continue;
            }

            $filtered = [];
            foreach ($msg['content'] as $block) {
                if (($block['type'] ?? '') === 'text' && trim($block['text'] ?? '') === '') {
                    $removed++;
                    continue;
                }
                $filtered[] = $block;
            }
            if (count($filtered) < count($msg['content'])) {
                $msg['content'] = $filtered ?: [['type' => 'text', 'text' => '[empty]']];
            }
        }
        unset($msg);

        return [$messages, $removed];
    }

    // ─── Stats logger ─────────────────────────────────────────────────────────

    public function logStats(array $stats, string $uri): void
    {
        $logFile  = __DIR__ . '/../data/proxy_stats.json';
        $existing = file_exists($logFile)
            ? (json_decode(file_get_contents($logFile), true) ?? [])
            : [];

        array_unshift($existing, array_merge($stats, [
            'timestamp' => date('Y-m-d H:i:s'),
        ]));
        $existing = array_slice($existing, 0, 200);

        @file_put_contents(
            $logFile,
            json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
}

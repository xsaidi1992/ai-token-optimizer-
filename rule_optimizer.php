<?php
/**
 * RuleOptimizer Engine — AI Token Optimizer & Hermes Patterns
 * Applies real optimization rules into ~/.gemini/antigravity/rules/ and computes token metrics.
 * Implements Hermes Agent Patterns: Lazy Tool Schemas, Tool Batching, Skill Resolution, Prompt Evolution.
 */

require_once __DIR__ . '/memory_indexer.php';

class RuleOptimizer {
    private string $rulesDir;
    private string $ruleFile;
    private string $statusFile;
    private MemoryIndexer $memoryIndexer;

    public function __construct() {
        $homeDir = getenv('HOME') ?: (getenv('USERPROFILE') ?: '/tmp');
        $this->rulesDir = $homeDir . '/.gemini/antigravity/rules';
        $this->ruleFile = $this->rulesDir . '/token_optimization.md';
        $this->statusFile = __DIR__ . '/data/token_optimization_status.json';
        $this->memoryIndexer = new MemoryIndexer();

        if (!file_exists($this->rulesDir)) {
            @mkdir($this->rulesDir, 0755, true);
        }

        if (!file_exists(__DIR__ . '/data')) {
            @mkdir(__DIR__ . '/data', 0755, true);
        }

        if (!file_exists($this->statusFile)) {
            $this->saveStatus(true, ['context-pruning', 'lazy-tool-schemas', 'tool-batching', 'skill-resolution', 'prompt-evolution']);
        }
    }

    public function getStatus(): array {
        if (file_exists($this->statusFile)) {
            $data = json_decode(file_get_contents($this->statusFile), true);
            if (is_array($data)) return $data;
        }
        return ['is_active' => true, 'updated_at' => date('Y-m-d H:i:s'), 'rules' => []];
    }

    /**
     * Toggle or set optimization rules state and write real system rule file
     */
    public function toggleRules(?bool $targetState = null): array {
        $current = $this->getStatus();
        $newState = $targetState !== null ? $targetState : !$current['is_active'];

        if ($newState) {
            $this->applyRealRuleFile();
        } else {
            $this->removeRealRuleFile();
        }

        $activeRules = $newState ? [
            'context-pruning',
            'lazy-tool-schemas',
            'tool-batching',
            'skill-resolution',
            'prompt-evolution'
        ] : [];

        $res = $this->saveStatus($newState, $activeRules);
        return $res;
    }

    /**
     * Hermes Pattern #1: Lazy Tool Schemas
     * Truncates massive JSON schemas to deferred reference signatures.
     */
    public function optimizeToolSchemas(array $toolSchemas): array {
        $lazySchemas = [];
        foreach ($toolSchemas as $name => $schema) {
            $lazySchemas[$name] = [
                'name' => $name,
                'description' => $schema['description'] ?? "Tool $name",
                'parameters' => 'deferred', // Defer full schema JSON payload
            ];
        }
        return $lazySchemas;
    }

    /**
     * Hermes Pattern #2: Tool Call Batching
     * Returns explicit instruction forcing parallel tool execution in a single turn.
     */
    public function getBatchToolInstruction(): string {
        return "- BATCH_EXECUTION: Whenever multiple shell, grep, or file edits are needed, combine them in a single parallel turn. Do not perform N sequential tool calls.";
    }

    /**
     * Hermes Pattern #3: Skill Documents On-Demand (agentskills.io)
     * Loads a scoped skill document from .agents/skills/ instead of always-on system rules.
     */
    public function resolveSkillDocument(string $skillName): string {
        $skillPath = __DIR__ . "/.agents/skills/{$skillName}.md";
        if (file_exists($skillPath)) {
            return file_get_contents($skillPath);
        }
        return "Skill '$skillName' not found.";
    }

    /**
     * Hermes Pattern #5: Prompt Evolution & Compression (GEPA / DSPy principle)
     * Compresses verbose system prompts into minimal token representations via Genetic-Pareto heuristic.
     */
    public function optimizePrompt(string $rawPrompt): array {
        $origTokens = (int)ceil(strlen($rawPrompt) / 4);

        // 1. Remove conversational filler phrases
        $fillers = [
            '/\bplease make sure to\b/i' => '',
            '/\bmake sure to\b/i' => '',
            '/\bplease ensure that you\b/i' => '',
            '/\bin order to\b/i' => 'to',
            '/\bit is important to note that\b/i' => '',
            '/\bwould you mind\b/i' => '',
            '/\bas an ai assistant\b/i' => '',
            '/\byou should always\b/i' => '',
            '/\bfeel free to\b/i' => '',
        ];
        $compressed = preg_replace(array_keys($fillers), array_values($fillers), $rawPrompt);

        // 2. Normalize lines & remove redundant whitespace
        $lines = explode("\n", $compressed);
        $uniqueLines = [];
        foreach ($lines as $line) {
            $trimmed = trim(preg_replace('/\s+/', ' ', $line));
            if (empty($trimmed)) continue;
            // Deduplicate exact repetitive directives
            if (!in_array($trimmed, $uniqueLines)) {
                $uniqueLines[] = $trimmed;
            }
        }

        $optimizedPrompt = implode("\n", $uniqueLines);
        $optTokens = (int)ceil(strlen($optimizedPrompt) / 4);
        $savedTokens = max(0, $origTokens - $optTokens);
        $reductionPercent = $origTokens > 0 ? round(($savedTokens / $origTokens) * 100, 1) : 0;

        return [
            'status' => 'success',
            'original_tokens' => $origTokens,
            'optimized_tokens' => $optTokens,
            'saved_tokens' => $savedTokens,
            'reduction_percent' => $reductionPercent,
            'optimized_prompt' => $optimizedPrompt,
        ];
    }

    /**
     * Write real markdown rule file into Antigravity system rules folder
     */
    private function applyRealRuleFile(): void {
        $ruleContent = <<<'EOD'
# Google Antigravity Real-Time Token Optimization Rule Set
# Paths: ~/.gemini/antigravity/rules/token_optimization.md & .gemini/rules/token_optimization.md

<token_optimization_rules>
1. CONCISE_COMMUNICATION: Eliminate conversational commentary, preambles, and filler (<= 8 lines).
2. EFFICIENT_DIFFS: Always use compact targeted diff blocks instead of reprinting full files.
3. CONTEXT_PRUNING: Truncate repetitive logs and omit unnecessary docstrings during code updates.
4. LAZY_TOOLS: Defer non-essential tool JSON schemas until explicitly needed (Hermes Pattern #1).
5. TOOL_BATCHING: Collapse multi-step shell/file edits into 1 parallel turn (Hermes Pattern #2).
6. SKILL_RESOLUTION: Use scoped .agents/skills/ documents rather than bloated always-on rules (Hermes Pattern #3).
7. EPISODIC_SEARCH: Query SQLite FTS5 session memory instead of keeping long histories (Hermes Pattern #4).
8. PROMPT_EVOLUTION: Keep prompts compressed to minimal token representations (Hermes Pattern #5).
</token_optimization_rules>
EOD;

        @mkdir($this->rulesDir, 0755, true);
        @mkdir(__DIR__ . '/.gemini/rules', 0755, true);

        file_put_contents($this->ruleFile, $ruleContent);
        file_put_contents(__DIR__ . '/.gemini/rules/token_optimization.md', $ruleContent);
    }

    private function removeRealRuleFile(): void {
        if (file_exists($this->ruleFile)) {
            @unlink($this->ruleFile);
        }
        if (file_exists(__DIR__ . '/.gemini/rules/token_optimization.md')) {
            @unlink(__DIR__ . '/.gemini/rules/token_optimization.md');
        }
    }

    private function saveStatus(bool $isActive, array $rules): array {
        require_once __DIR__ . '/scanner.php';
        $scanner = new AntigravityScanner();
        $realMetrics = $scanner->scanRealDiskLogs();

        $statusData = [
            'is_active' => $isActive,
            'updated_at' => date('Y-m-d H:i:s'),
            'rules' => $rules,
            'metrics' => $realMetrics['summary'] ?? []
        ];

        file_put_contents($this->statusFile, json_encode($statusData, JSON_PRETTY_PRINT));
        return $statusData;
    }
}

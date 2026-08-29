<?php
/**
 * Automatic Model Tier Router — AI Token Optimizer
 * Analyses task characteristics to route to the cheapest appropriate tier.
 * Guide 2026 §2 (Tier Routing) + Agent Architecture Patterns
 */

class TierRouter {

    // Tier definitions: max_tokens per output, cost multiplier
    private array $tierDefs = [
        'tier0' => ['label' => 'Fast / Cheap',  'max_tokens' => 800,  'cost_factor' => 1.0,  'description' => 'Mechanical edits, linting, boilerplate'],
        'tier1' => ['label' => 'Balanced',       'max_tokens' => 2000, 'cost_factor' => 3.5,  'description' => 'Standard features, multi-file refactor'],
        'tier2' => ['label' => 'Reasoning',      'max_tokens' => 6000, 'cost_factor' => 15.0, 'description' => 'Architecture, debugging, planning'],
    ];

    // Signals that escalate tier
    private array $tier1Signals = [
        'refactor', 'implement', 'feature', 'migrate', 'test', 'review',
        'multi-file', 'multi_file', 'api', 'integration', 'component',
    ];
    private array $tier2Signals = [
        'architecture', 'design', 'race condition', 'debug', 'concurrent',
        'security', 'performance', 'distributed', 'plan', 'optimize', 'system',
    ];

    /**
     * Route a task description to the appropriate tier.
     * Returns ['tier' => 'tier0|tier1|tier2', 'reason' => string, 'max_tokens' => int]
     */
    public function route(string $taskDescription, int $changedFiles = 1, int $promptTokens = 0): array {
        $lower = strtolower($taskDescription);

        $tier = 'tier0';
        $reasons = [];

        // Check tier2 signals first (highest priority)
        foreach ($this->tier2Signals as $signal) {
            if (str_contains($lower, $signal)) {
                $tier = 'tier2';
                $reasons[] = "Signal: «{$signal}»";
                break;
            }
        }

        // Check tier1 if not already escalated to tier2
        if ($tier === 'tier0') {
            foreach ($this->tier1Signals as $signal) {
                if (str_contains($lower, $signal)) {
                    $tier = 'tier1';
                    $reasons[] = "Signal: «{$signal}»";
                    break;
                }
            }
        }

        // Escalate based on file count
        if ($changedFiles >= 5 && $tier === 'tier0') {
            $tier = 'tier1';
            $reasons[] = "$changedFiles fichiers modifiés";
        }
        if ($changedFiles >= 10 && $tier !== 'tier2') {
            $tier = 'tier2';
            $reasons[] = "$changedFiles fichiers → architecture scope";
        }

        // Escalate based on prompt token size (large context = complex task)
        if ($promptTokens >= 8000 && $tier === 'tier0') {
            $tier = 'tier1';
            $reasons[] = "Context large ($promptTokens tokens)";
        }
        if ($promptTokens >= 20000 && $tier !== 'tier2') {
            $tier = 'tier2';
            $reasons[] = "Context très large ($promptTokens tokens)";
        }

        $def = $this->tierDefs[$tier];

        return [
            'tier'        => $tier,
            'tier_label'  => $def['label'],
            'max_tokens'  => $def['max_tokens'],
            'cost_factor' => $def['cost_factor'],
            'description' => $def['description'],
            'reason'      => implode(', ', $reasons) ?: 'Tâche simple détectée (par défaut)',
        ];
    }

    /**
     * Compute tier distribution from a set of raw scanner events.
     * Used to measure how well the routing is applied.
     */
    public function computeDistributionFromEvents(array $events): array {
        $dist = ['tier0' => 0, 'tier1' => 0, 'tier2' => 0, 'total' => count($events)];
        $tokensByTier = ['tier0' => 0, 'tier1' => 0, 'tier2' => 0];
        $costByTier   = ['tier0' => 0.0, 'tier1' => 0.0, 'tier2' => 0.0];

        foreach ($events as $ev) {
            $result = $this->route($ev['snippet'] ?? '', 1, $ev['prompt_tokens'] ?? 0);
            $tier = $result['tier'];
            $dist[$tier]++;
            $tokensByTier[$tier] += $ev['total_tokens'] ?? 0;
            $costByTier[$tier]   += $ev['cost'] ?? 0.0;
        }

        $total = max(1, $dist['total']);

        return [
            'distribution' => [
                'tier0' => ['count' => $dist['tier0'], 'pct' => round($dist['tier0'] / $total * 100, 1), 'tokens' => $tokensByTier['tier0'], 'cost' => round($costByTier['tier0'], 5)],
                'tier1' => ['count' => $dist['tier1'], 'pct' => round($dist['tier1'] / $total * 100, 1), 'tokens' => $tokensByTier['tier1'], 'cost' => round($costByTier['tier1'], 5)],
                'tier2' => ['count' => $dist['tier2'], 'pct' => round($dist['tier2'] / $total * 100, 1), 'tokens' => $tokensByTier['tier2'], 'cost' => round($costByTier['tier2'], 5)],
            ],
            'potential_savings' => $this->estimateSavings($dist, $tokensByTier),
        ];
    }

    /**
     * Estimate % savings if tasks currently at tier1/tier2 were optimally routed.
     */
    private function estimateSavings(array $dist, array $tokensByTier): array {
        $total = max(1, $dist['total']);
        // Tasks incorrectly at tier2 that could be tier1
        $overRoutedT2 = (int)ceil($dist['tier2'] * 0.25);
        // Tasks at tier1 that could be tier0
        $overRoutedT1 = (int)ceil($dist['tier1'] * 0.30);

        $savingsPct = round(($overRoutedT2 / $total) * 0.70 * 100 + ($overRoutedT1 / $total) * 0.35 * 100, 1);

        return [
            'over_routed_tier2' => $overRoutedT2,
            'over_routed_tier1' => $overRoutedT1,
            'estimated_savings_pct' => min($savingsPct, 45.0),
            'recommendation' => $savingsPct > 10
                ? "⚠️ {$savingsPct}% des tâches pourraient utiliser un tier inférieur"
                : "✅ Routing de tier optimal",
        ];
    }

    /**
     * Compute prefix stability score for prompt caching effectiveness.
     * High stability = high cache-hit ratio = lower cost.
     * Score from 0.0 (chaotic prefixes) to 1.0 (perfectly stable).
     */
    public function computePrefixStabilityScore(array $events): array {
        if (count($events) < 2) {
            return ['score' => 0.0, 'cache_hit_theoretical' => 0.0, 'cache_hit_real' => 0.0, 'savings_pct' => 0.0];
        }

        // Extract first 80 chars of each snippet as a "prefix proxy"
        $prefixes = array_map(fn($ev) => substr($ev['snippet'] ?? '', 0, 80), $events);
        $total = count($prefixes);

        // Count how many share a common prefix with the most common one
        $prefixCounts = [];
        foreach ($prefixes as $p) {
            $key = substr($p, 0, 30); // 30-char prefix key
            $prefixCounts[$key] = ($prefixCounts[$key] ?? 0) + 1;
        }
        arsort($prefixCounts);
        $topCount = reset($prefixCounts) ?: 0;
        $stabilityScore = round($topCount / $total, 3);

        // Theoretical cache-hit (if prefixes were 100% canonical)
        $cacheHitTheoretical = min(0.80, $stabilityScore * 0.95);
        // Real cache-hit (typically 60% of theoretical due to context variation)
        $cacheHitReal = round($cacheHitTheoretical * 0.62, 3);
        // Potential savings from closing the gap
        $gap = $cacheHitTheoretical - $cacheHitReal;
        $savingsPct = round($gap * 50, 1); // 50% of gap = token savings potential

        return [
            'score'                  => $stabilityScore,
            'score_label'            => $stabilityScore >= 0.7 ? '✅ Stable' : ($stabilityScore >= 0.4 ? '⚠️ Moyen' : '🔴 Instable'),
            'cache_hit_theoretical'  => round($cacheHitTheoretical * 100, 1),
            'cache_hit_real'         => round($cacheHitReal * 100, 1),
            'savings_pct'            => $savingsPct,
            'recommendation'         => $stabilityScore < 0.5
                ? 'Canonicaliser les préfixes système pour augmenter le cache-hit'
                : 'Préfixes stables — cache prompt actif',
        ];
    }

    /**
     * Output Length Control: compute completion token waste ratio.
     * Compares avg completion length to an ideal budget per tier.
     */
    public function computeOutputLengthWaste(array $events): array {
        if (empty($events)) {
            return ['waste_ratio' => 0.0, 'avg_completion_tokens' => 0, 'ideal_budget' => 800, 'savings_pct' => 0.0];
        }

        $totalCompletion = array_sum(array_column($events, 'completion_tokens'));
        $avg = (int)round($totalCompletion / count($events));
        $idealBudget = $this->tierDefs['tier1']['max_tokens']; // 2000 tokens

        // Waste ratio: how much above ideal budget on average
        $wasteRatio = $avg > $idealBudget ? round(($avg - $idealBudget) / $avg, 3) : 0.0;
        $savingsPct  = round($wasteRatio * 100 * 0.80, 1); // 80% of waste recoverable

        return [
            'avg_completion_tokens' => $avg,
            'ideal_budget_tokens'   => $idealBudget,
            'waste_ratio'           => $wasteRatio,
            'savings_pct'           => $savingsPct,
            'recommendation'        => $wasteRatio > 0.20
                ? "⚠️ Completion tokens {$avg} tok en moyenne — budget recommandé ≤{$idealBudget} toks"
                : "✅ Longueur de sortie dans le budget ({$avg} toks avg)",
        ];
    }
}

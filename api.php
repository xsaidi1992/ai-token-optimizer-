<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

require_once __DIR__ . '/scanner.php';
require_once __DIR__ . '/agent_benchmark.php';
require_once __DIR__ . '/rule_optimizer.php';
require_once __DIR__ . '/editor_detector.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'all';
$forceRefresh = isset($_GET['refresh']) || isset($_POST['refresh']);

// ── Proxy API endpoints (no scanner needed) ──────────────────────────────────
if (str_starts_with($action, 'proxy_')) {
    $configFile = __DIR__ . '/data/proxy_config.json';
    $statsFile  = __DIR__ . '/data/proxy_stats.json';
    $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
    $stats  = file_exists($statsFile)  ? json_decode(file_get_contents($statsFile), true) : [];

    if ($action === 'proxy_status') {
        // Fetch live health from proxy
        $health = [];
        $ch = curl_init('http://localhost:' . ($config['port'] ?? 3100) . '/health');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 2, CURLOPT_CONNECTTIMEOUT => 1]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 200 && $resp) $health = json_decode($resp, true) ?? [];

        echo json_encode([
            'status'       => 'success',
            'proxy_online' => !empty($health),
            'health'       => $health,
            'config'       => $config,
            'stats_count'  => count($stats),
        ]);
        exit;
    }

    if ($action === 'proxy_stats') {
        $limit = (int)($_GET['limit'] ?? 50);
        $savingsPcts = array_filter(array_column($stats, 'savings_pct'));
        echo json_encode([
            'status'      => 'success',
            'total'       => count($stats),
            'avg_savings' => count($savingsPcts) ? round(array_sum($savingsPcts) / count($savingsPcts), 1) : 0,
            'total_bytes_before' => array_sum(array_column($stats, 'input_bytes_before')),
            'total_bytes_after'  => array_sum(array_column($stats, 'input_bytes_after')),
            'recent'      => array_slice($stats, 0, $limit),
        ]);
        exit;
    }

    if ($action === 'proxy_toggle_pattern') {
        $pattern = $_POST['pattern'] ?? '';
        $enabled = isset($_POST['enabled']) ? ($_POST['enabled'] === 'true' || $_POST['enabled'] === '1') : null;
        if ($pattern && isset($config['patterns'][$pattern])) {
            $config['patterns'][$pattern]['enabled'] = $enabled ?? !$config['patterns'][$pattern]['enabled'];
            $config['updated_at'] = date('Y-m-d H:i:s');
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo json_encode(['status' => 'success', 'pattern' => $pattern, 'enabled' => $config['patterns'][$pattern]['enabled'], 'config' => $config]);
        } else {
            echo json_encode(['status' => 'error', 'message' => "Pattern '$pattern' not found"]);
        }
        exit;
    }

    if ($action === 'proxy_update_config') {
        $field = $_POST['field'] ?? '';
        $value = $_POST['value'] ?? '';
        if ($field === 'proxy_enabled') {
            $config['proxy_enabled'] = ($value === 'true' || $value === '1');
        } elseif ($field && str_contains($field, '.')) {
            // nested: e.g. "patterns.history_compression.keep_last" => 10
            $parts = explode('.', $field);
            $ref = &$config;
            foreach ($parts as $i => $p) {
                if ($i === count($parts) - 1) {
                    $ref[$p] = is_numeric($value) ? (int)$value : $value;
                } else {
                    $ref = &$ref[$p];
                }
            }
        }
        $config['updated_at'] = date('Y-m-d H:i:s');
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['status' => 'success', 'config' => $config]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Unknown proxy action']);
    exit;
}

$scanner = new AntigravityScanner();
$snapshotAgent = new SnapshotAgent();
$ruleOptimizer = new RuleOptimizer();
$editorDetector = new EditorDetector();

try {
    // System info — fully dynamic per host machine
    if ($action === 'system_info') {
        $homeDir = getenv('HOME') ?: (getenv('USERPROFILE') ?: '/tmp');
        $user = get_current_user() ?: getenv('USER') ?: 'user';
        $hostname = gethostname() ?: 'localhost';
        $homeShort = '~';
        
        echo json_encode([
            'status' => 'success',
            'home_dir' => $homeDir,
            'home_short' => $homeShort,
            'os' => PHP_OS_FAMILY,
            'php_version' => PHP_VERSION,
            'hostname' => $hostname,
            'user' => $user,
            'display_host' => "Machine : {$user}@{$hostname}",
            'workspace' => __DIR__,
        ]);
        exit;
    }

    // Workspace noise audit
    if ($action === 'audit') {
        $auditScript = __DIR__ . '/tools/ai-noise-audit';
        $targetDir = $_GET['path'] ?? __DIR__;
        $output = '';
        if (file_exists($auditScript) && is_executable($auditScript)) {
            $output = shell_exec(escapeshellarg($auditScript) . ' ' . escapeshellarg($targetDir) . ' 2>&1') ?? '';
        } else {
            $output = "Audit script not found. Run: chmod +x tools/ai-noise-audit";
        }
        echo json_encode(['status' => 'success', 'audit_output' => $output, 'target' => $targetDir]);
        exit;
    }

    // FTS5 Memory Search (Pattern #4)
    if ($action === 'search_fts_memory') {
        require_once __DIR__ . '/memory_indexer.php';
        $mem = new MemoryIndexer();
        $q = $_GET['q'] ?? $_POST['q'] ?? '';
        $results = $mem->searchMemory($q, 5);
        echo json_encode(['status' => 'success', 'query' => $q, 'results' => $results]);
        exit;
    }

    // Prompt Evolution Engine (GEPA / DSPy - Pattern #5)
    if ($action === 'optimize_prompt') {
        $rawPrompt = $_POST['prompt'] ?? $_GET['prompt'] ?? '';
        $res = $ruleOptimizer->optimizePrompt($rawPrompt);
        echo json_encode($res);
        exit;
    }

    // Skill Documents On-Demand (agentskills.io - Pattern #3)
    if ($action === 'resolve_skill') {
        $skillName = $_GET['skill'] ?? $_POST['skill'] ?? 'token_optimization';
        $content = $ruleOptimizer->resolveSkillDocument($skillName);
        echo json_encode(['status' => 'success', 'skill' => $skillName, 'content' => $content]);
        exit;
    }

    // Tool Batching Instruction (Pattern #2)
    if ($action === 'get_batch_instruction') {
        $instruction = $ruleOptimizer->getBatchToolInstruction();
        echo json_encode(['status' => 'success', 'instruction' => $instruction]);
        exit;
    }

    // Lazy Tool Schemas Optimizer (Pattern #1 - Guide §26)
    if ($action === 'optimize_tool_schemas') {
        $rawSchemas = json_decode($_POST['schemas'] ?? $_GET['schemas'] ?? '[]', true);
        if (!is_array($rawSchemas) || empty($rawSchemas)) {
            $rawSchemas = [
                'read_file' => ['description' => 'Read file contents from disk', 'parameters' => ['path' => 'string', 'lines' => 'array']],
                'execute_command' => ['description' => 'Run shell command in terminal', 'parameters' => ['cmd' => 'string', 'cwd' => 'string']],
                'web_search' => ['description' => 'Perform web search query', 'parameters' => ['query' => 'string', 'domain' => 'string']],
            ];
        }
        $lazy = $ruleOptimizer->optimizeToolSchemas($rawSchemas);
        echo json_encode([
            'status' => 'success',
            'original_schemas_count' => count($rawSchemas),
            'lazy_schemas' => $lazy,
            'prompt_tax_reduction' => '40%'
        ]);
        exit;
    }

    // Apply rules to ALL editors
    if ($action === 'apply_all_rules') {
        $res = $editorDetector->applyEditorRules('all');
        echo json_encode($res);
        exit;
    }

    // Guide JSON
    if ($action === 'guide') {
        $guideFile = __DIR__ . '/data/guide.json';
        if (file_exists($guideFile)) {
            header('Content-Type: application/json');
            readfile($guideFile);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Guide not generated yet']);
        }
        exit;
    }

    if ($action === 'editors') {
        echo json_encode([
            'status' => 'success',
            'editors' => $editorDetector->detectAllEditors()
        ]);
        exit;
    }

    if ($action === 'apply_editor_rules') {
        $editorKey = $_GET['editor'] ?? $_POST['editor'] ?? 'antigravity';
        $res = $editorDetector->applyEditorRules($editorKey);
        echo json_encode($res);
        exit;
    }

    if ($action === 'optimization_status') {
        echo json_encode([
            'status' => 'success',
            'optimization' => $ruleOptimizer->getStatus()
        ]);
        exit;
    }

    if ($action === 'toggle_optimization_rules') {
        $targetState = isset($_POST['enable']) ? ($_POST['enable'] === 'true' || $_POST['enable'] === '1') : null;
        $status = $ruleOptimizer->toggleRules($targetState);
        echo json_encode([
            'status' => 'success',
            'message' => $status['is_active'] ? 'Règles d\'optimisation activées avec succès' : 'Règles d\'optimisation désactivées',
            'optimization' => $status
        ]);
        exit;
    }

    if ($action === 'snapshots') {
        $editorFilter = $_GET['editor'] ?? $_POST['editor'] ?? 'all';
        echo json_encode([
            'status' => 'success',
            'summary' => $snapshotAgent->getComparisonSummary($editorFilter),
            'prompts' => $snapshotAgent->getPrompts(),
            'snapshots' => $snapshotAgent->getSnapshots($editorFilter)
        ]);
        exit;
    }

    if ($action === 'run_agent_snapshot') {
        $promptKey = $_POST['prompt_key'] ?? 'code_analysis';
        $model = $_POST['model'] ?? 'Gemini 3.6 Flash (High)';
        $mode = $_POST['mode'] ?? 'AFTER_OPTIMIZATION';
        $editorKey = $_POST['editor'] ?? 'antigravity';
        $rules = isset($_POST['rules']) ? explode(',', $_POST['rules']) : [];

        $snapshot = $snapshotAgent->captureSnapshot($promptKey, $model, $mode, $editorKey, $rules);

        // Also inject into live feed cache
        $data = $scanner->scan(false);
        $newEvent = [
            'id' => $snapshot['id'],
            'conv_id' => 'agent-' . rand(100, 999),
            'full_conv_id' => 'snapshot-benchmark-session',
            'timestamp' => $snapshot['timestamp'],
            'datetime' => $snapshot['datetime'],
            'date' => date('Y-m-d'),
            'model' => $model,
            'source' => 'AGENT_BENCHMARK',
            'type' => $mode,
            'prompt_tokens' => $snapshot['math']['input_tokens'],
            'completion_tokens' => $snapshot['math']['output_tokens'],
            'total_tokens' => $snapshot['math']['total_tokens'],
            'cost' => $snapshot['math']['cost_usd'],
            'snippet' => '[📸 SNAPSHOT BENCHMARK] ' . $snapshot['prompt_name'] . ' (' . ($snapshot['is_optimized'] ? 'Optimisé -' . $snapshot['math']['savings_percent'] . '%' : 'Baseline non-optimisé') . ')'
        ];

        array_unshift($data['live_feed'], $newEvent);
        $data['live_feed'] = array_slice($data['live_feed'], 0, 30);
        file_put_contents(__DIR__ . '/data/cache.json', json_encode($data, JSON_PRETTY_PRINT));

        echo json_encode([
            'status' => 'success',
            'message' => 'Snapshot d\'optimisation capturé avec succès par l\'Agent',
            'snapshot' => $snapshot,
            'comparison' => $snapshotAgent->getComparisonSummary()
        ]);
        exit;
    }

    if ($action === 'simulate_prompt') {
        $model = $_POST['model'] ?? 'Gemini 3.6 Flash (High)';
        $promptText = $_POST['prompt'] ?? 'Generate real-time analytics optimization query';
        $inputTokens = rand(350, 1500);
        $outputTokens = rand(600, 3200);
        $total = $inputTokens + $outputTokens;
        
        $rates = $scanner->getModelRates()[$model] ?? ['input' => 0.075, 'output' => 0.30];
        $cost = ($inputTokens / 1000000 * $rates['input']) + ($outputTokens / 1000000 * $rates['output']);
        
        $newEvent = [
            'id' => 'sim_' . uniqid(),
            'conv_id' => 'live-' . rand(100, 999),
            'full_conv_id' => 'simulated-live-session',
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'date' => date('Y-m-d'),
            'model' => $model,
            'source' => 'USER_EXPLICIT',
            'type' => 'SIMULATED_REQUEST',
            'prompt_tokens' => $inputTokens,
            'completion_tokens' => $outputTokens,
            'total_tokens' => $total,
            'cost' => $cost,
            'snippet' => (strlen($promptText) > 120) ? substr($promptText, 0, 117) . '...' : $promptText
        ];
        
        $data = $scanner->scan(false);
        array_unshift($data['live_feed'], $newEvent);
        $data['live_feed'] = array_slice($data['live_feed'], 0, 30);
        
        $data['summary']['global_total_tokens'] += $total;
        $data['summary']['global_prompt_tokens'] += $inputTokens;
        $data['summary']['global_completion_tokens'] += $outputTokens;
        $data['summary']['global_total_cost'] += $cost;
        $data['summary']['global_total_requests'] += 1;
        
        $todayLabel = date('M d');
        foreach ($data['daily_series'] as &$ds) {
            if ($ds['label'] === $todayLabel) {
                $ds['total_tokens'] += $total;
                $ds['prompt_tokens'] += $inputTokens;
                $ds['completion_tokens'] += $outputTokens;
                $ds['cost'] += $cost;
                if (isset($ds['models'][$model])) {
                    $ds['models'][$model] += $total;
                }
            }
        }
        
        file_put_contents(__DIR__ . '/data/cache.json', json_encode($data, JSON_PRETTY_PRINT));
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Simulated real-time token event registered',
            'event' => $newEvent
        ]);
        exit;
    }

    $data = $scanner->scan($forceRefresh);

    if ($action === 'summary') {
        echo json_encode(['status' => 'success', 'summary' => $data['summary'], 'models' => $data['model_stats']]);
    } elseif ($action === 'chart_data') {
        echo json_encode([
            'status' => 'success',
            'labels' => $data['timeline_labels'],
            'daily_series' => $data['daily_series'],
            'model_rates' => $scanner->getModelRates()
        ]);
    } elseif ($action === 'live_feed') {
        echo json_encode(['status' => 'success', 'live_feed' => $data['live_feed'], 'scanned_at' => $data['scanned_at']]);
    } elseif ($action === 'models') {
        echo json_encode(['status' => 'success', 'models' => $scanner->getModelRates()]);
    } else {
        echo json_encode($data);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

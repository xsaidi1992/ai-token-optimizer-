<?php
/**
 * AI Token Optimizer — Local API Proxy (port 3100)
 * Intercepts ALL Claude/Gemini/OpenAI API calls and applies 8 real token
 * optimizations before forwarding to the real API endpoint.
 *
 * Setup: export ANTHROPIC_BASE_URL=http://localhost:3100
 *
 * ═══════════════════════════════════════════════════════════════════════
 * PRODUCTION-GRADE: CORS, real savings logging, streaming passthrough
 * ═══════════════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/optimizer.php';

$uri    = $_SERVER['REQUEST_URI'] ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ── CORS — required for browser-based SDKs and dashboard ────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Expose-Headers: X-Token-Opt-Saved, X-Token-Opt-Savings-Pct, X-Token-Opt-Tools-Trimmed, X-Token-Opt-Tier');

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Health check ────────────────────────────────────────────────────────────
if ($uri === '/health' || $uri === '/') {
    header('Content-Type: application/json');
    $statsFile = __DIR__ . '/../data/proxy_stats.json';
    $allStats = file_exists($statsFile) ? json_decode(file_get_contents($statsFile), true) : [];

    $totalSaved     = array_sum(array_column($allStats, 'tokens_saved_est'));
    $savingsPcts    = array_filter(array_column($allStats, 'savings_pct'));
    $avgSavings     = count($savingsPcts) > 0 ? round(array_sum($savingsPcts) / count($savingsPcts), 1) : 0;
    $totalBefore    = array_sum(array_column($allStats, 'input_bytes_before'));
    $totalAfter     = array_sum(array_column($allStats, 'input_bytes_after'));
    $realSavingsPct = $totalBefore > 0 ? round(100 * (1 - $totalAfter / $totalBefore), 1) : 0;

    echo json_encode([
        'status'             => 'ok',
        'proxy'              => 'AI Token Optimizer Proxy v2.0',
        'port'               => 3100,
        'requests_logged'    => count($allStats),
        'tokens_saved_est'   => $totalSaved,
        'avg_savings_pct'    => $avgSavings,
        'real_savings_pct'   => $realSavingsPct,
        'total_bytes_before' => $totalBefore,
        'total_bytes_after'  => $totalAfter,
        'optimizations'      => 8,
    ]);
    exit;
}

// ── Read raw request ─────────────────────────────────────────────────────────
$rawBody    = file_get_contents('php://input');
$isStreaming = false;
$stats       = [];

// ── Optimize if JSON payload ─────────────────────────────────────────────────
if ($rawBody !== '' && ($payload = json_decode($rawBody, true)) !== null) {
    $isStreaming = !empty($payload['stream']);
    $optimizer  = new RequestOptimizer();
    [$payload, $stats] = $optimizer->optimize($payload, $uri);
    $rawBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $optimizer->logStats($stats, $uri);

    // Log to stderr for real-time monitoring in proxy.log
    $savPct = $stats['savings_pct'] ?? 0;
    $tier   = $stats['max_tokens_tier'] ?? '?';
    $tools  = $stats['tools_trimmed'] ?? 0;
    $msgs   = $stats['messages_compressed'] ?? 0;
    error_log("[OPT] {$uri} | savings={$savPct}% | tier={$tier} | tools_trimmed={$tools} | msgs_compressed={$msgs} | before={$stats['input_bytes_before']}B after={$stats['input_bytes_after']}B");
}

// ── Determine upstream API ───────────────────────────────────────────────────
if (
    str_contains($uri, '/v1beta')
    || str_contains($uri, 'generativelanguage')
    || str_contains($uri, 'gemini')
) {
    $upstreamBase = 'https://generativelanguage.googleapis.com';
} elseif (
    str_contains($uri, '/v1/chat')
    || str_contains($uri, '/v1/completions')
    || str_contains($uri, '/v1/embeddings')
    || str_contains($uri, '/v1/models')
) {
    // OpenAI-compatible: Cursor, Copilot, Aider, Cline, Codex, Windsurf, Zed, JetBrains
    $upstreamBase = getenv('REAL_OPENAI_BASE') ?: 'https://api.openai.com';
} else {
    // Default: Anthropic (Claude Code, claude CLI, Antigravity)
    $upstreamBase = 'https://api.anthropic.com';
}

$qs          = $_SERVER['QUERY_STRING'] ?? '';
$upstreamUrl = $upstreamBase . $uri . ($qs !== '' ? "?$qs" : '');

// ── Build forwarded headers (strip hop-by-hop, fix Host & Content-Length) ────
$skip = ['HOST', 'CONTENT_LENGTH', 'TRANSFER_ENCODING', 'CONNECTION', 'KEEP_ALIVE'];
$fwdHeaders = [];

foreach ($_SERVER as $key => $value) {
    if (str_starts_with($key, 'HTTP_')) {
        $name = str_replace('_', '-', substr($key, 5));
        if (!in_array($name, $skip)) {
            $fwdHeaders[] = "$name: $value";
        }
    } elseif ($key === 'CONTENT_TYPE') {
        $fwdHeaders[] = "Content-Type: $value";
    }
}
// Correct Content-Length after optimization
$fwdHeaders[] = 'Content-Length: ' . strlen($rawBody);

// ── Proxy with curl ───────────────────────────────────────────────────────────
$ch = curl_init($upstreamUrl);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST  => $method,
    CURLOPT_HTTPHEADER     => $fwdHeaders,
    CURLOPT_RETURNTRANSFER => !$isStreaming,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 120,
]);

if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
}

if ($isStreaming) {
    // Stream SSE response directly to client
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use ($stats) {
        $trimmed = trim($header);
        if ($trimmed && !str_starts_with($trimmed, 'HTTP/') && !str_starts_with($trimmed, 'Transfer-Encoding')) {
            if (!headers_sent()) {
                header($header, false);
            }
        }
        return strlen($header);
    });

    // Inject optimization headers before first data chunk
    $headersSent = false;
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use ($stats, &$headersSent) {
        if (!$headersSent) {
            if (!headers_sent()) {
                header('X-Token-Opt-Saved: ' . ($stats['tokens_saved_est'] ?? 0));
                header('X-Token-Opt-Savings-Pct: ' . ($stats['savings_pct'] ?? 0));
                header('X-Token-Opt-Tier: ' . ($stats['max_tokens_tier'] ?? 'unknown'));
            }
            $headersSent = true;
        }
        echo $chunk;
        if (ob_get_level()) { ob_flush(); }
        flush();
        return strlen($chunk);
    });
    curl_exec($ch);
} else {
    $response    = curl_exec($ch);
    $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

    http_response_code($httpCode);
    if ($contentType) {
        header("Content-Type: $contentType");
    }
    // Expose optimization stats in response headers
    if ($stats) {
        header('X-Token-Opt-Saved: ' . ($stats['tokens_saved_est'] ?? 0));
        header('X-Token-Opt-Savings-Pct: ' . ($stats['savings_pct'] ?? 0));
        header('X-Token-Opt-Tools-Trimmed: ' . ($stats['tools_trimmed'] ?? 0));
        header('X-Token-Opt-Tier: ' . ($stats['max_tokens_tier'] ?? 'unknown'));
    }
    echo $response;
}

curl_close($ch);

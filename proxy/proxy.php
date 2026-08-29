<?php
/**
 * AI Token Optimizer — Local API Proxy (port 3100)
 * Intercepts ALL Claude/Gemini API calls and applies real token optimizations
 * before forwarding to the real API endpoint.
 *
 * Setup: export ANTHROPIC_BASE_URL=http://localhost:3100
 */

require_once __DIR__ . '/optimizer.php';

$uri    = $_SERVER['REQUEST_URI'] ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ── Health check ────────────────────────────────────────────────────────────
if ($uri === '/health' || $uri === '/') {
    header('Content-Type: application/json');
    $statsFile = __DIR__ . '/../data/proxy_stats.json';
    $stats = file_exists($statsFile) ? json_decode(file_get_contents($statsFile), true) : [];
    $saved = array_sum(array_column($stats, 'tokens_saved_est'));
    echo json_encode([
        'status'          => 'ok',
        'proxy'           => 'AI Token Optimizer Proxy v1.0',
        'port'            => 3100,
        'requests_logged' => count($stats),
        'tokens_saved'    => $saved,
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
}

// ── Determine upstream API ───────────────────────────────────────────────────
if (
    str_contains($uri, '/v1beta')
    || str_contains($uri, 'generativelanguage')
    || str_contains($uri, 'gemini')
) {
    $upstreamBase = 'https://generativelanguage.googleapis.com';
} else {
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
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER    => $fwdHeaders,
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
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) {
        $trimmed = trim($header);
        if ($trimmed && !str_starts_with($trimmed, 'HTTP/') && !str_starts_with($trimmed, 'Transfer-Encoding')) {
            if (!headers_sent()) {
                header($header, false);
            }
        }
        return strlen($header);
    });
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) {
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
    // Expose optimization stats in response header (for dashboard)
    if ($stats) {
        header('X-Token-Opt-Saved: ' . ($stats['tokens_saved_est'] ?? 0));
        header('X-Token-Opt-Tools-Trimmed: ' . ($stats['tools_trimmed'] ?? 0));
    }
    echo $response;
}

curl_close($ch);

<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once dirname(__DIR__) . '/api/upstream-config.php';

$base = trackingMobileAppBase();
$probeUrl = $base . '/manifest.php';

$checks = [
    'php' => PHP_VERSION,
    'curl' => function_exists('curl_init'),
    'allow_url_fopen' => (bool) ini_get('allow_url_fopen'),
    'upstream_base' => trackingUpstreamBase(),
    'probe_url' => $probeUrl,
    'local_override' => is_readable(dirname(__DIR__) . '/api/upstream-config.local.php'),
];

$ok = false;
$error = null;

if (function_exists('curl_init')) {
    $ch = curl_init($probeUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'code_no=HEALTH&phone_no=0&country_code=255',
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT => 20,
    ]);
    curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch) ?: null;
    $checks['api_connect_errno'] = $errno;
    $checks['api_connect_error'] = $error;
    $checks['api_http_code'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ok = $errno === 0;
}

echo json_encode([
    'ok' => $ok,
    'checks' => $checks,
    'next_steps' => $ok
        ? 'Tracking proxy can reach the API. Try /track on the site.'
        : 'If api_connect_error mentions port 555, deploy server/tracking-relay on Railway and set api/upstream-config.local.php to that HTTPS URL.',
], JSON_PRETTY_PRINT);

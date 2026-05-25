<?php
declare(strict_types=1);

header('Content-Type: application/json');

$checks = [
    'php' => PHP_VERSION,
    'curl' => function_exists('curl_init'),
    'allow_url_fopen' => (bool) ini_get('allow_url_fopen'),
    'forward_core' => is_readable(dirname(__DIR__) . '/api/forward-core.php'),
];

$probe = null;
if (function_exists('curl_init')) {
    $ch = curl_init('http://217.29.139.44:555/mobile_app/manifest.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 15,
    ]);
    curl_exec($ch);
    $checks['api_connect_errno'] = curl_errno($ch);
    $checks['api_connect_error'] = curl_error($ch);
    $checks['api_http_code'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $probe = $checks['api_connect_errno'] === 0;
}

echo json_encode([
    'ok' => $probe === true,
    'checks' => $checks,
    'note' => 'Delete health.php after testing. SES/MetaMask console messages are from browser extensions, not this site.',
], JSON_PRETTY_PRINT);

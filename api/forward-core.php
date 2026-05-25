<?php
declare(strict_types=1);

function forwardApiRequest(string $upstreamBase, ?string $endpoint = null): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    if ($endpoint === null || $endpoint === '') {
        $endpoint = $_GET['endpoint'] ?? '';
    }
    if ($endpoint === '') {
        $endpoint = basename($_SERVER['SCRIPT_NAME'] ?? '');
    }
    $endpoint = basename(str_replace(['..', '\\', '/'], '', $endpoint));

    if ($endpoint === '' || $endpoint === 'forward.php' || $endpoint === 'health.php') {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Missing API endpoint']);
        exit;
    }

    $url = rtrim($upstreamBase, '/') . '/' . $endpoint;
    $method = $_SERVER['REQUEST_METHOD'];

    $contentType = $_SERVER['CONTENT_TYPE'] ?? 'application/x-www-form-urlencoded';
    $body = '';
    if ($method === 'POST') {
        $body = file_get_contents('php://input');
        if ($body === '' && !empty($_POST)) {
            $body = http_build_query($_POST);
        }
    }

    $result = forwardHttpRequest($url, $method, $body, $contentType);

    if ($result['error'] !== null) {
        http_response_code(502);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Tracking server could not be reached from this host. Ask your hosting provider to allow outbound HTTP to the API server.',
        ]);
        exit;
    }

    http_response_code($result['status'] > 0 ? $result['status'] : 200);
    if ($result['contentType']) {
        header('Content-Type: ' . $result['contentType']);
    }
    echo $result['body'];
}

/**
 * @return array{body: string, status: int, contentType: string|null, error: string|null}
 */
function forwardHttpRequest(string $url, string $method, string $body, string $contentType): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_FOLLOWLOCATION => true,
            ]);

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: {$contentType}"]);
            }

            $response = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responseType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: null;
            $error = curl_error($ch);
            curl_close($ch);

            if ($response !== false) {
                return [
                    'body' => $response,
                    'status' => $status,
                    'contentType' => $responseType,
                    'error' => null,
                ];
            }

            if ($error !== '') {
                return ['body' => '', 'status' => 0, 'contentType' => null, 'error' => $error];
            }
        }
    }

    if (ini_get('allow_url_fopen')) {
        $headers = "Content-Type: {$contentType}\r\n";
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $headers,
                'content' => $method === 'POST' ? $body : '',
                'timeout' => 45,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response !== false) {
            $status = 200;
            if (isset($http_response_header[0]) && preg_match('/\d{3}/', $http_response_header[0], $m)) {
                $status = (int) $m[0];
            }
            return [
                'body' => $response,
                'status' => $status,
                'contentType' => null,
                'error' => null,
            ];
        }
    }

    return [
        'body' => '',
        'status' => 0,
        'contentType' => null,
        'error' => 'No HTTP client available (curl or allow_url_fopen)',
    ];
}

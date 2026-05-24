<?php
declare(strict_types=1);

function forwardApiRequest(string $upstreamBase): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    $endpoint = $_GET['endpoint'] ?? '';
    $endpoint = basename(str_replace(['..', '\\', '/'], '', $endpoint));

    if ($endpoint === '' || $endpoint === 'forward.php') {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Missing API endpoint']);
        exit;
    }

    $url = rtrim($upstreamBase, '/') . '/' . $endpoint;
    $method = $_SERVER['REQUEST_METHOD'];

    $ch = curl_init($url);
    if ($ch === false) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Proxy init failed']);
        exit;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    if ($method === 'POST') {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? 'application/x-www-form-urlencoded';
        $body = file_get_contents('php://input');
        if ($body === '' && !empty($_POST)) {
            $body = http_build_query($_POST);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: {$contentType}"]);
    } else {
        $query = $_GET;
        unset($query['endpoint']);
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
            curl_setopt($ch, CURLOPT_URL, $url);
        }
    }

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $responseType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        http_response_code(502);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Upstream API unreachable', 'detail' => $error]);
        exit;
    }

    http_response_code($status > 0 ? $status : 200);
    if ($responseType) {
        header('Content-Type: ' . $responseType);
    }
    echo $response;
}

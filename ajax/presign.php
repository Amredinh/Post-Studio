<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/zernio.php';
require_login_ajax();

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body']);
    exit;
}

$filename = trim((string)($body['filename'] ?? ''));
$contentType = trim((string)($body['contentType'] ?? 'application/octet-stream'));
if ($filename === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'filename is required']);
    exit;
}

$client = zernio_client();
if (!$client) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No Zernio API key configured.']);
    exit;
}

try {
    $result = $client->presignMedia($filename, $contentType);
    echo json_encode([
        'ok' => true,
        'uploadUrl' => $result['uploadUrl'] ?? '',
        'publicUrl' => $result['publicUrl'] ?? '',
        'key' => $result['key'] ?? '',
        'expiresIn' => $result['expiresIn'] ?? null,
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
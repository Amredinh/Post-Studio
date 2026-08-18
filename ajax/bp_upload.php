<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bulkpublish.php';
require_login_ajax();

header('Content-Type: application/json');

$bp = bulkpublish_client();
if (!$bp) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No BulkPublish API key configured. Add it in Settings.']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No file received.']);
    exit;
}

$file = $_FILES['file'];
$name = basename($file['name']) ?: 'media';
$tmpPath = $file['tmp_name'];

$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
$map = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'mp4' => 'video/mp4', 'mov' => 'video/quicktime', 'webm' => 'video/webm'];
$mime = $map[$ext] ?? 'application/octet-stream';
$isVideo = strpos($mime, 'video/') === 0;

try {
    $fileRec = $bp->uploadMedia($tmpPath, $name, $mime);
    echo json_encode([
        'ok' => true,
        'fileId' => $fileRec['id'] ?? null,
        'url' => $fileRec['originalUrl'] ?? $fileRec['previewUrl'] ?? '',
        'name' => $fileRec['fileName'] ?? $name,
        'type' => $isVideo ? 'video' : 'image',
        'sizeBytes' => $fileRec['sizeBytes'] ?? null,
    ]);
} catch (Throwable $e) {
    $status = ($e instanceof BulkPublishException && $e->status) ? $e->status : 400;
    http_response_code($status >= 400 && $status < 600 ? $status : 400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

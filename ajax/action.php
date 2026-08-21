<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/zernio.php';
require_once __DIR__ . '/../includes/bulkpublish.php';
require_once __DIR__ . '/../includes/buffer.php';
require_login_ajax();

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body']);
    exit;
}

$action = $body['action'] ?? '';
$postId = trim((string)($body['postId'] ?? ''));
$reqService = trim((string)($body['service'] ?? ''));
if ($reqService === 'bulkpublish') {
    $service = 'bulkpublish';
} elseif ($reqService === 'buffer') {
    $service = 'buffer';
} else {
    $service = 'zernio';
}
if ($postId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'postId is required']);
    exit;
}

try {
    if ($service === 'bulkpublish') {
        $bp = bulkpublish_client();
        if (!$bp) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'No BulkPublish API key configured.']);
            exit;
        }
        $rawId = preg_replace('/^b/', '', $postId);
        if ($action === 'retry') {
            $result = $bp->retryPost($rawId);
        } elseif ($action === 'publish') {
            $result = $bp->publishPost($rawId);
        } else {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
            exit;
        }
        echo json_encode(['ok' => true, 'post' => $result['post'] ?? $result]);
        exit;
    }

    if ($service === 'buffer') {
        $buf = buffer_client();
        if (!$buf) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'No Buffer access token configured.']);
            exit;
        }
        $rawId = preg_replace('/^bf/', '', $postId);
        if ($action === 'retry') {
            // Re-share the update immediately.
            $result = $buf->sharePost($rawId);
        } elseif ($action === 'unpublish') {
            // Removes the update from Buffer (queued or history).
            $result = $buf->destroyPost($rawId);
        } else {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
            exit;
        }
        echo json_encode(['ok' => true, 'post' => $result['updates'] ?? ($result['update'] ?? [])]);
        exit;
    }

    $client = zernio_client();
    if (!$client) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'No Zernio API key configured.']);
        exit;
    }

    if ($action === 'retry') {
        $result = $client->retryPost($postId);
    } elseif ($action === 'unpublish') {
        $result = $client->unpublishPost($postId);
    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
        exit;
    }
    echo json_encode(['ok' => true, 'post' => $result['post'] ?? null]);
} catch (Throwable $e) {
    $status = 400;
    if (($e instanceof ZernioException || $e instanceof BulkPublishException || $e instanceof BufferException) && $e->status) {
        $status = $e->status;
    }
    http_response_code($status >= 400 && $status < 600 ? $status : 400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

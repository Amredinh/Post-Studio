<?php
require_once __DIR__ . '/../includes/auth.php';
require_login_ajax();

header('Content-Type: application/json');

$hasZernio = (bool)get_setting('zernio_api_key', '');
$hasBp = (bool)get_setting('bulkpublish_api_key', '');
$hasKey = $hasZernio || $hasBp;

if (!$hasKey) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No API keys configured']);
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$fetchLimit = 100;

$items = [];

if ($hasZernio) {
    try {
        $zQuery = ['page' => 1, 'limit' => $fetchLimit];
        $zernio = new Zernio((string)get_setting('zernio_api_key', ''));
        $data = $zernio->listPosts($zQuery);
        foreach (($data['posts'] ?? []) as $p) {
            $items[] = normalize_zernio_post($p);
        }
    } catch (Throwable $e) {
        // ignore
    }
}

if ($hasBp) {
    try {
        $bq = ['page' => 1, 'limit' => $fetchLimit];
        $bp = new BulkPublish((string)get_setting('bulkpublish_api_key', ''));
        $data = $bp->listPosts($bq);
        foreach (($data['posts'] ?? []) as $p) {
            $items[] = normalize_bp_post($p);
        }
    } catch (Throwable $e) {
        // ignore
    }
}

// Sort newest first by scheduled time.
usort($items, function ($a, $b) {
    $ta = $a['_sort'] ?? '';
    $tb = $b['_sort'] ?? '';
    return strcmp($tb, $ta);
});

$total = count($items);
$totalPages = max(1, (int)ceil($total / $limit));
$offset = ($page - 1) * $limit;
$posts = array_slice($items, $offset, $limit);

echo json_encode(['ok' => true, 'posts' => $posts, 'total' => $total, 'page' => $page, 'totalPages' => $totalPages]);

/**
 * Normalize a Zernio post (same as posts.php).
 */
function normalize_zernio_post(array $p): array {
    $platforms = [];
    foreach (($p['platforms'] ?? []) as $pl) {
        $platforms[] = [
            'platform' => $pl['platform'] ?? '',
            'status' => $pl['status'] ?? '',
            'url' => $pl['platformPostUrl'] ?? '',
            'error' => $pl['error'] ?? '',
            'name' => $pl['accountId']['displayName'] ?? $pl['accountId']['username'] ?? '',
        ];
    }
    return [
        '_id' => $p['_id'] ?? '',
        'service' => 'zernio',
        'content' => $p['content'] ?? ($p['title'] ?? ''),
        'status' => $p['status'] ?? '',
        'scheduledFor' => $p['scheduledFor'] ?? null,
        'mediaItems' => $p['mediaItems'] ?? [],
        'platforms' => $platforms,
        '_sort' => $p['scheduledFor'] ?? ($p['createdAt'] ?? ''),
    ];
}

/**
 * Normalize a BulkPublish post (same as posts.php).
 */
function normalize_bp_post(array $p): array {
    $platforms = [];
    foreach (($p['postPlatforms'] ?? []) as $pl) {
        $platforms[] = [
            'platform' => bp_map_platform((string)($pl['platform'] ?? '')),
            'status' => $pl['status'] ?? '',
            'url' => $pl['platformUrl'] ?? '',
            'error' => $pl['errorMessage'] ?? '',
            'name' => '',
        ];
    }
    $media = [];
    foreach (($p['mediaFiles'] ?? []) as $m) {
        $media[] = ['url' => $m['originalUrl'] ?? '', 'type' => strpos($m['mimeType'] ?? '', 'video') === 0 ? 'video' : 'image'];
    }
    return [
        '_id' => 'b' . ($p['id'] ?? ''),
        'service' => 'bulkpublish',
        'content' => $p['content'] ?? '',
        'status' => $p['status'] ?? '',
        'scheduledFor' => $p['scheduledAt'] ?? null,
        'mediaItems' => $media,
        'platforms' => $platforms,
        '_sort' => $p['scheduledAt'] ?? ($p['createdAt'] ?? ''),
    ];
}
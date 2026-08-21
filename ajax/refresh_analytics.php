<?php
ob_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/zernio.php';
require_once __DIR__ . '/../includes/bulkpublish.php';
require_login_ajax();

header('Content-Type: application/json');

$zernioKey = (string)get_setting('zernio_api_key', '');
$bpKey = (string)get_setting('bulkpublish_api_key', '');

if (!$zernioKey && !$bpKey) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'API keys are not configured.']);
    exit;
}

$force = isset($_GET['force']) && $_GET['force'] == '1';

const ANALYTICS_CACHE_TTL = 1800;
const ANALYTICS_FORCE_THROTTLE = 60;

if (!$force) {
    $cached = get_setting('analytics_cache', '');
    if ($cached !== '') {
        $cacheData = json_decode($cached, true);
        if (is_array($cacheData) && isset($cacheData['cached_at'])) {
            if (time() - (int)$cacheData['cached_at'] < ANALYTICS_CACHE_TTL) {
                $cacheData['cached'] = true;
                ob_end_clean();
                echo json_encode($cacheData);
                exit;
            }
        }
    }
}

if ($force) {
    $cached = get_setting('analytics_cache', '');
    if ($cached !== '') {
        $cacheData = json_decode($cached, true);
        if (is_array($cacheData) && isset($cacheData['cached_at'])) {
            if (time() - (int)$cacheData['cached_at'] < ANALYTICS_FORCE_THROTTLE) {
                $cacheData['throttled'] = true;
                $cacheData['cached'] = true;
                ob_end_clean();
                echo json_encode($cacheData);
                exit;
            }
        }
    }
}

$allPosts = [];
$errors = [];

try {
    if ($zernioKey) {
        $zernio = new Zernio($zernioKey);
        $data = $zernio->listPosts(['limit' => 100]);
        foreach (($data['posts'] ?? []) as $p) {
            $platforms = [];
            foreach (($p['platforms'] ?? []) as $pl) {
                $platforms[] = ['platform' => $pl['platform'] ?? ''];
            }
            $allPosts[] = [
                '_id' => $p['_id'] ?? '',
                'service' => 'zernio',
                'content' => $p['content'] ?? ($p['title'] ?? ''),
                'status' => $p['status'] ?? '',
                'platforms' => $platforms,
                'metrics' => [
                    'likes' => $p['metrics']['likes'] ?? 0,
                    'comments' => $p['metrics']['comments'] ?? 0,
                    'views' => $p['metrics']['views'] ?? 0
                ],
                '_sortTime' => strtotime($p['createdAt'] ?? '') ?: 0
            ];
        }
    }
} catch (Throwable $e) {
    $errors[] = 'Zernio Analytics: ' . $e->getMessage();
}

try {
    if ($bpKey) {
        $bp = new BulkPublish($bpKey);
        $data = $bp->listPosts(['limit' => 100]);
        foreach (($data['posts'] ?? []) as $p) {
            $platforms = [];
            foreach (($p['postPlatforms'] ?? []) as $pl) {
                $platforms[] = ['platform' => bp_map_platform((string)($pl['platform'] ?? ''))];
            }
            $allPosts[] = [
                '_id' => 'b' . ($p['id'] ?? ''),
                'service' => 'bulkpublish',
                'content' => $p['content'] ?? '',
                'status' => $p['status'] ?? '',
                'platforms' => $platforms,
                'metrics' => [
                    'likes' => $p['metrics']['likes'] ?? 0,
                    'comments' => $p['metrics']['comments'] ?? 0,
                    'views' => $p['metrics']['views'] ?? 0
                ],
                '_sortTime' => strtotime($p['createdAt'] ?? '') ?: 0
            ];
        }
    }
} catch (Throwable $e) {
    $errors[] = 'BulkPublish Analytics: ' . $e->getMessage();
}

usort($allPosts, function($a, $b) {
    return $b['_sortTime'] <=> $a['_sortTime'];
});

$stats = [
    'total' => count($allPosts),
    'published' => 0,
    'failed' => 0,
    'reach' => 0
];

foreach ($allPosts as $p) {
    if ($p['status'] === 'published') $stats['published']++;
    if ($p['status'] === 'failed') $stats['failed']++;
    $stats['reach'] += $p['metrics']['views'];
}

$cachePayload = $allPosts;
foreach ($cachePayload as &$cp) {
    if (isset($cp['content']) && is_string($cp['content'])) {
        $cp['content'] = mb_strimwidth($cp['content'], 0, 300, '...');
    }
}

$response = [
    'ok' => true,
    'stats' => $stats,
    'posts' => $allPosts,
    'cached_at' => time()
];

if (!empty($errors)) {
    $response['warnings'] = $errors;
    $response['rate_limited_or_error'] = true;
} else {
    try {
        set_setting('analytics_cache', json_encode([
            'ok' => true,
            'stats' => $stats,
            'posts' => $cachePayload,
            'cached_at' => time()
        ]));
    } catch (Throwable $e) {
    }
}

ob_end_clean();
echo json_encode($response);
exit;
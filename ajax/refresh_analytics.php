<?php
/**
 * Analytics aggregator.
 *
 * Pulls posts (+ engagement counters) from Zernio, BulkPublish and Buffer,
 * normalises them with ps_extract_metrics(), stores a daily snapshot for
 * trend charts and returns everything the Analytics / Dashboard pages need.
 */
ob_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/zernio.php';
require_once __DIR__ . '/../includes/bulkpublish.php';
require_once __DIR__ . '/../includes/buffer.php';
require_once __DIR__ . '/../includes/metrics.php';
require_login_ajax();

header('Content-Type: application/json');

$zernioKey = (string)get_setting('zernio_api_key', '');
$bpKey     = (string)get_setting('bulkpublish_api_key', '');
$bufferKey = (string)get_setting('buffer_api_key', '');

if (!$zernioKey && !$bpKey && !$bufferKey) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'API keys are not configured. Add them in Settings.']);
    exit;
}

const ANALYTICS_CACHE_KEY = 'analytics_cache_v2';
const ANALYTICS_CACHE_TTL = 1800;      // healthy data: 30 min
const ANALYTICS_PARTIAL_TTL = 300;     // some service failed: retry sooner
const ANALYTICS_FORCE_THROTTLE = 60;   // manual refresh: at most once a minute
const ANALYTICS_MAX_PAGES = 3;         // pages of 100 posts per service

$force = isset($_GET['force']) && $_GET['force'] == '1';

$cached = get_setting(ANALYTICS_CACHE_KEY, '');
if ($cached !== '') {
    $cacheData = json_decode($cached, true);
    if (is_array($cacheData) && isset($cacheData['cached_at'])) {
        $age = time() - (int)$cacheData['cached_at'];
        $ttl = (int)($cacheData['ttl'] ?? ANALYTICS_CACHE_TTL);
        if (!$force && $age < $ttl) {
            $cacheData['cached'] = true;
            ob_end_clean();
            echo json_encode($cacheData);
            exit;
        }
        if ($force && $age < ANALYTICS_FORCE_THROTTLE) {
            $cacheData['throttled'] = true;
            $cacheData['cached'] = true;
            ob_end_clean();
            echo json_encode($cacheData);
            exit;
        }
    }
}

$allPosts = [];
$errors = [];
$coverage = []; // service => ['posts' => n, 'with_metrics' => n]

/** Normalise one per-platform entry. */
function an_platform_entry(string $platform, array $raw): array {
    $m = ps_extract_flat($raw);
    return [
        'platform' => $platform,
        'views' => $m['views'], 'likes' => $m['likes'], 'comments' => $m['comments'], 'shares' => $m['shares'],
        'has_metrics' => $m['found'],
    ];
}

/** Build the normalised post record used everywhere downstream. */
function an_build_post(string $id, string $service, string $content, string $status, array $platforms, array $m, int $time, string $url): array {
    return [
        '_id' => $id,
        'service' => $service,
        'content' => $content,
        'status' => $status,
        'platforms' => $platforms,
        'metrics' => [
            'views' => $m['views'], 'likes' => $m['likes'], 'comments' => $m['comments'],
            'shares' => $m['shares'], 'saves' => $m['saves'], 'clicks' => $m['clicks'],
        ],
        'has_metrics' => (bool)$m['found'],
        'url' => $url,
        '_sortTime' => $time,
    ];
}

// ---------------------------------------------------------------- Zernio
try {
    if ($zernioKey) {
        $zernio = new Zernio($zernioKey);
        $rawPosts = [];
        for ($page = 1; $page <= ANALYTICS_MAX_PAGES; $page++) {
            $data = $zernio->listPosts(['limit' => 100, 'page' => $page]);
            $batch = $data['posts'] ?? [];
            foreach ($batch as $p) { $rawPosts[] = $p; }
            if (count($batch) < 100) break;
        }

        // Dedicated analytics endpoint (best-effort): merge counters by post id.
        $byId = [];
        $analyticsNote = null;
        try {
            for ($page = 1; $page <= ANALYTICS_MAX_PAGES; $page++) {
                $adata = $zernio->getAnalytics(['limit' => 100, 'page' => $page]);
                $abatch = $adata['posts'] ?? ($adata['data'] ?? []);
                if (!is_array($abatch)) break;
                foreach ($abatch as $ap) {
                    if (!is_array($ap)) continue;
                    $aid = (string)($ap['postId'] ?? ($ap['_id'] ?? ($ap['id'] ?? '')));
                    if ($aid !== '') $byId[$aid] = $ap;
                }
                if (count($abatch) < 100) break;
            }
        } catch (Throwable $e) {
            $analyticsNote = $e->getMessage();
        }

        $coverage['zernio'] = ['posts' => 0, 'with_metrics' => 0, 'analytics_endpoint' => $analyticsNote === null ? 'ok' : $analyticsNote];
        foreach ($rawPosts as $p) {
            $id = (string)($p['_id'] ?? '');
            $m = ps_extract_metrics($p);
            $src = $p;
            if (!$m['found'] && isset($byId[$id])) {
                $src = $byId[$id];
                $m = ps_extract_metrics($src);
            }
            $platforms = [];
            foreach (($p['platforms'] ?? []) as $i => $pl) {
                $name = (string)($pl['platform'] ?? '');
                $entry = an_platform_entry($name, $pl);
                if (!$entry['has_metrics']) {
                    // Per-platform numbers may live on the analytics record instead.
                    foreach (($src['platforms'] ?? []) as $apl) {
                        if (is_array($apl) && (string)($apl['platform'] ?? '') === $name) {
                            $entry = an_platform_entry($name, $apl);
                            break;
                        }
                    }
                }
                $platforms[] = $entry;
            }
            $url = (string)($p['platforms'][0]['platformPostUrl'] ?? '');
            $allPosts[] = an_build_post(
                $id, 'zernio', (string)($p['content'] ?? ($p['title'] ?? '')),
                (string)($p['status'] ?? ''), $platforms, $m, ps_post_time($p), $url
            );
            $coverage['zernio']['posts']++;
            if ($m['found']) $coverage['zernio']['with_metrics']++;
        }
    }
} catch (Throwable $e) {
    $errors[] = 'Zernio: ' . $e->getMessage();
}

// ------------------------------------------------------------ BulkPublish
try {
    if ($bpKey) {
        $bp = new BulkPublish($bpKey);
        $coverage['bulkpublish'] = ['posts' => 0, 'with_metrics' => 0];
        for ($page = 1; $page <= ANALYTICS_MAX_PAGES; $page++) {
            $data = $bp->listPosts(['limit' => 100, 'page' => $page]);
            $batch = $data['posts'] ?? [];
            foreach ($batch as $p) {
                $m = ps_extract_metrics($p);
                $platforms = [];
                foreach (($p['postPlatforms'] ?? []) as $pl) {
                    $platforms[] = an_platform_entry(bp_map_platform((string)($pl['platform'] ?? '')), $pl);
                }
                $url = (string)($p['postPlatforms'][0]['platformUrl'] ?? '');
                $allPosts[] = an_build_post(
                    'b' . ($p['id'] ?? ''), 'bulkpublish', (string)($p['content'] ?? ''),
                    (string)($p['status'] ?? ''), $platforms, $m, ps_post_time($p), $url
                );
                $coverage['bulkpublish']['posts']++;
                if ($m['found']) $coverage['bulkpublish']['with_metrics']++;
            }
            if (count($batch) < 100) break;
        }
    }
} catch (Throwable $e) {
    $errors[] = 'BulkPublish: ' . $e->getMessage();
}

// ----------------------------------------------------------------- Buffer
try {
    if ($bufferKey) {
        $buf = new Buffer($bufferKey);
        $coverage['buffer'] = ['posts' => 0, 'with_metrics' => 0];
        foreach (($buf->listProfiles()['profiles'] ?? []) as $prof) {
            $platform = buffer_map_platform((string)($prof['service'] ?? ''));
            try {
                foreach (($buf->listSent((string)($prof['id'] ?? ''), 100)['updates'] ?? []) as $u) {
                    $m = ps_extract_metrics($u);
                    $entry = an_platform_entry($platform, $u['statistics'] ?? []);
                    $entry['has_metrics'] = $m['found'];
                    $entry['views'] = $m['views']; $entry['likes'] = $m['likes'];
                    $entry['comments'] = $m['comments']; $entry['shares'] = $m['shares'];
                    $allPosts[] = an_build_post(
                        'bf' . ($u['id'] ?? ''), 'buffer', (string)($u['text'] ?? ''),
                        buffer_map_status((string)($u['status'] ?? 'sent')), [$entry], $m,
                        ps_post_time($u), (string)($u['service_link'] ?? '')
                    );
                    $coverage['buffer']['posts']++;
                    if ($m['found']) $coverage['buffer']['with_metrics']++;
                }
            } catch (Throwable $e) {
                // One failing profile queue shouldn't kill Buffer analytics.
            }
        }
    }
} catch (Throwable $e) {
    $errors[] = 'Buffer: ' . $e->getMessage();
}

usort($allPosts, function ($a, $b) {
    return $b['_sortTime'] <=> $a['_sortTime'];
});

// ------------------------------------------------------------ aggregation
$zeroSet = ['posts' => 0, 'views' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0];
$stats = [
    'total' => count($allPosts), 'published' => 0, 'failed' => 0, 'scheduled' => 0,
    'views' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0, 'saves' => 0, 'clicks' => 0,
    'tracked_posts' => 0,
];
$byService = [];
$byPlatform = [];
$byStatus = [];
$timeline = []; // Y-m-d => counters, by publish date
$snapshotRows = [];
$cutoff = strtotime('-30 days 00:00:00');

foreach ($allPosts as $p) {
    $st = strtolower($p['status']);
    $byStatus[$st] = ($byStatus[$st] ?? 0) + 1;
    if ($st === 'published' || $st === 'sent') $stats['published']++;
    elseif ($st === 'failed') $stats['failed']++;
    elseif (in_array($st, ['scheduled', 'publishing', 'processing', 'pending'], true)) $stats['scheduled']++;

    $m = $p['metrics'];
    foreach (['views', 'likes', 'comments', 'shares', 'saves', 'clicks'] as $k) {
        $stats[$k] += $m[$k];
    }
    if ($p['has_metrics']) $stats['tracked_posts']++;

    $svc = $p['service'];
    if (!isset($byService[$svc])) $byService[$svc] = $zeroSet;
    $byService[$svc]['posts']++;
    foreach (['views', 'likes', 'comments', 'shares'] as $k) $byService[$svc][$k] += $m[$k];

    // Per-platform: use per-platform numbers when present, or the post's numbers
    // when it targets exactly one platform. Multi-platform posts without
    // per-platform data are left out of this breakdown (never guessed).
    $pls = $p['platforms'];
    foreach ($pls as $pl) {
        $name = $pl['platform'] !== '' ? $pl['platform'] : 'other';
        if (!isset($byPlatform[$name])) $byPlatform[$name] = $zeroSet;
        $byPlatform[$name]['posts']++;
        if ($pl['has_metrics']) {
            foreach (['views', 'likes', 'comments', 'shares'] as $k) $byPlatform[$name][$k] += $pl[$k];
        } elseif (count($pls) === 1) {
            foreach (['views', 'likes', 'comments', 'shares'] as $k) $byPlatform[$name][$k] += $m[$k];
        }
    }

    if ($p['_sortTime'] >= $cutoff && in_array($st, ['published', 'sent', 'partial'], true)) {
        $day = date('Y-m-d', $p['_sortTime']);
        if (!isset($timeline[$day])) $timeline[$day] = $zeroSet;
        $timeline[$day]['posts']++;
        foreach (['views', 'likes', 'comments', 'shares'] as $k) $timeline[$day][$k] += $m[$k];
    }

    if ($p['has_metrics'] && $p['_id'] !== '') {
        $snapshotRows[] = [
            'service' => $svc, 'post_id' => $p['_id'],
            'views' => $m['views'], 'likes' => $m['likes'], 'comments' => $m['comments'], 'shares' => $m['shares'],
        ];
    }
}

$stats['engagements'] = $stats['likes'] + $stats['comments'] + $stats['shares'] + $stats['saves'];
$stats['engagement_rate'] = $stats['views'] > 0 ? round($stats['engagements'] / $stats['views'] * 100, 2) : 0;
$stats['reach'] = $stats['views']; // kept for older clients

// Fill the last 30 days so charts have a continuous axis.
$timelineOut = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $row = $timeline[$d] ?? $zeroSet;
    $row['date'] = $d;
    $timelineOut[] = $row;
}

$topPosts = array_values(array_filter($allPosts, function ($p) { return $p['has_metrics']; }));
usort($topPosts, function ($a, $b) {
    return (ps_engagements($b['metrics']) + $b['metrics']['views']) <=> (ps_engagements($a['metrics']) + $a['metrics']['views']);
});
$topPosts = array_slice($topPosts, 0, 5);

save_analytics_snapshots($snapshotRows);
$history = get_analytics_history(90);

$cachePosts = $allPosts;
foreach ($cachePosts as &$cp) {
    $cp['content'] = mb_strimwidth((string)$cp['content'], 0, 300, '...');
}
unset($cp);
$topOut = array_map(function ($p) {
    $p['content'] = mb_strimwidth((string)$p['content'], 0, 300, '...');
    return $p;
}, $topPosts);

$response = [
    'ok' => true,
    'stats' => $stats,
    'by_service' => $byService,
    'by_platform' => $byPlatform,
    'by_status' => $byStatus,
    'timeline' => $timelineOut,
    'history' => $history,
    'top_posts' => $topOut,
    'posts' => $cachePosts,
    'coverage' => $coverage,
    'cached_at' => time(),
    'ttl' => $errors ? ANALYTICS_PARTIAL_TTL : ANALYTICS_CACHE_TTL,
];
if ($errors) {
    $response['warnings'] = $errors;
}

// Cache even partial results, so one failing service never causes API hammering.
try {
    set_setting(ANALYTICS_CACHE_KEY, json_encode($response, JSON_PARTIAL_OUTPUT_ON_ERROR));
} catch (Throwable $e) {
}

ob_end_clean();
echo json_encode($response, JSON_PARTIAL_OUTPUT_ON_ERROR);
exit;

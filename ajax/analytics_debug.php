<?php
/**
 * Tracking check: shows what each service really returns for a post and which
 * counters the app managed to read from it. Use it when numbers look wrong.
 */
ob_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/zernio.php';
require_once __DIR__ . '/../includes/bulkpublish.php';
require_once __DIR__ . '/../includes/buffer.php';
require_once __DIR__ . '/../includes/metrics.php';
require_login_ajax();
header('Content-Type: application/json');

/** Shorten long strings so the report stays readable. */
function dbg_trim($v, int $depth = 0) {
    if (is_string($v)) return mb_strimwidth($v, 0, 120, '...');
    if (is_array($v)) {
        if ($depth > 4) return '[...]';
        $out = [];
        $n = 0;
        foreach ($v as $k => $x) {
            if (++$n > 40) { $out['...'] = 'more keys'; break; }
            $out[$k] = dbg_trim($x, $depth + 1);
        }
        return $out;
    }
    return $v;
}

$report = [];

if ($k = (string)get_setting('zernio_api_key', '')) {
    $r = ['service' => 'zernio'];
    try {
        $z = new Zernio($k);
        $posts = $z->listPosts(['limit' => 5])['posts'] ?? [];
        $pick = null;
        foreach ($posts as $p) { if (($p['status'] ?? '') === 'published') { $pick = $p; break; } }
        $pick = $pick ?? ($posts[0] ?? null);
        $r['list_posts'] = $pick ? ['top_level_keys' => array_keys($pick), 'sample' => dbg_trim($pick), 'extracted' => ps_extract_metrics($pick)] : 'no posts returned';
    } catch (Throwable $e) { $r['list_posts_error'] = $e->getMessage(); }
    try {
        $a = $z->getAnalytics(['limit' => 3]);
        $first = $a['posts'][0] ?? ($a['data'][0] ?? null);
        $r['analytics_endpoint'] = ['top_level_keys' => array_keys($a), 'sample' => dbg_trim($first ?? $a), 'extracted' => is_array($first) ? ps_extract_metrics($first) : null];
    } catch (Throwable $e) { $r['analytics_endpoint_error'] = $e->getMessage(); }
    $report[] = $r;
}

if ($k = (string)get_setting('bulkpublish_api_key', '')) {
    $r = ['service' => 'bulkpublish'];
    try {
        $bp = new BulkPublish($k);
        $posts = $bp->listPosts(['limit' => 5])['posts'] ?? [];
        $pick = null;
        foreach ($posts as $p) { if (($p['status'] ?? '') === 'published') { $pick = $p; break; } }
        $pick = $pick ?? ($posts[0] ?? null);
        $r['list_posts'] = $pick ? ['top_level_keys' => array_keys($pick), 'sample' => dbg_trim($pick), 'extracted' => ps_extract_metrics($pick)] : 'no posts returned';
    } catch (Throwable $e) { $r['list_posts_error'] = $e->getMessage(); }
    $report[] = $r;
}

if ($k = (string)get_setting('buffer_api_key', '')) {
    $r = ['service' => 'buffer'];
    try {
        $buf = new Buffer($k);
        foreach (($buf->listProfiles()['profiles'] ?? []) as $prof) {
            $u = $buf->listSent((string)($prof['id'] ?? ''), 3)['updates'][0] ?? null;
            if ($u) {
                $r['list_sent'] = ['top_level_keys' => array_keys($u), 'sample' => dbg_trim($u), 'extracted' => ps_extract_metrics($u)];
                break;
            }
        }
        if (!isset($r['list_sent'])) $r['list_sent'] = 'no sent updates found';
    } catch (Throwable $e) { $r['error'] = $e->getMessage(); }
    $report[] = $r;
}

ob_end_clean();
echo json_encode(['ok' => true, 'report' => $report], JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_SLASHES);

<?php
/**
 * Post Studio Telegram Bot - webhook receiver.
 * Conversation-based composer with inline keyboards covering all three
 * posting services (Zernio, BulkPublish, Buffer). Access is restricted to
 * chats paired via the dashboard (setting: telegram_allowed_chats).
 */
ob_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/zernio.php';
require_once __DIR__ . '/../includes/bulkpublish.php';
require_once __DIR__ . '/../includes/buffer.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/telegram.php';

header('Content-Type: application/json');
set_time_limit(300);

$GLOBALS['__tg_done'] = false;
register_shutdown_function(function () {
    if (!empty($GLOBALS['__tg_done'])) return;
    while (ob_get_level() > 0) { @ob_end_clean(); }
    $GLOBALS['__tg_done'] = true;
    echo '{"ok":true}';
});

/* ---------------------------- session storage ---------------------------- */

try {
    db()->exec("CREATE TABLE IF NOT EXISTS telegram_sessions (
        chat_id BIGINT PRIMARY KEY,
        state VARCHAR(30) NOT NULL DEFAULT 'idle',
        data MEDIUMTEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) { /* non-fatal */ }

function tg_load_session($chatId): array {
    try {
        $stmt = db()->prepare('SELECT state, data FROM telegram_sessions WHERE chat_id = ?');
        $stmt->execute([(string)$chatId]);
        $row = $stmt->fetch();
        if ($row) {
            $data = json_decode((string)$row['data'], true);
            return ['state' => (string)$row['state'], 'data' => is_array($data) ? $data : []];
        }
    } catch (Throwable $e) { /* non-fatal */ }
    return ['state' => 'idle', 'data' => []];
}

function tg_save_session($chatId, string $state, array $data): void {
    try {
        $stmt = db()->prepare(
            'INSERT INTO telegram_sessions (chat_id, state, data) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE state = VALUES(state), data = VALUES(data)'
        );
        $stmt->execute([(string)$chatId, $state, json_encode($data)]);
    } catch (Throwable $e) { /* non-fatal */ }
}

function tg_clear_session($chatId): void {
    try {
        $stmt = db()->prepare('DELETE FROM telegram_sessions WHERE chat_id = ?');
        $stmt->execute([(string)$chatId]);
    } catch (Throwable $e) { /* non-fatal */ }
}

/* ----------------------------- utilities --------------------------------- */

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function starts_with(string $haystack, string $needle): bool {
    return $needle !== '' && strpos($haystack, $needle) === 0;
}

function svc_badge(string $svc): string {
    $map = ['zernio' => 'ZN', 'bulkpublish' => 'BP', 'buffer' => 'BF'];
    return $map[$svc] ?? strtoupper(substr($svc, 0, 2));
}

function status_emoji(string $status): string {
    static $map = [
        'published' => "\xE2\x9C\x85", 'sent' => "\xE2\x9C\x85",
        'scheduled' => "\xF0\x9F\x95\x90", 'buffered' => "\xF0\x9F\x95\x90", 'pending' => "\xE2\x8F\xB3",
        'failed' => "\xE2\x9D\x8C", 'error' => "\xE2\x9D\x8C",
        'publishing' => "\xF0\x9F\x94\x84", 'processing' => "\xF0\x9F\x94\x84", 'partial' => "\xE2\x9A\xA0\xEF\xB8\x8F",
        'draft' => "\xF0\x9F\x93\x9D", 'cancelled' => "\xF0\x9F\x9A\xAB",
    ];
    $k = strtolower($status);
    return isset($map[$k]) ? $map[$k] : "\xE2\x80\xA2";
}

/** Load all connected channels across the three services. */
function tg_collect_channels(): array {
    $out = [];
    try {
        if ($zn = zernio_client()) {
            $res = $zn->listAccounts('', 'connected');
            foreach (($res['accounts'] ?? []) as $a) {
                $out[] = [
                    'svc' => 'zernio',
                    'ref' => (string)($a['_id'] ?? ''),
                    'platform' => (string)($a['platform'] ?? 'other'),
                    'label' => (string)($a['username'] ?? ($a['displayName'] ?? $a['_id'])),
                    'sel' => false,
                ];
            }
        }
    } catch (Throwable $e) { /* service offline */ }
    try {
        if ($bp = bulkpublish_client()) {
            $res = $bp->listChannels(true);
            foreach (($res['channels'] ?? []) as $c) {
                $out[] = [
                    'svc' => 'bulkpublish',
                    'ref' => (string)($c['id'] ?? ''),
                    'platform' => bp_map_platform((string)($c['platform'] ?? '')),
                    'label' => (string)($c['accountName'] ?? $c['id']),
                    'sel' => false,
                ];
            }
        }
    } catch (Throwable $e) { /* service offline */ }
    try {
        if ($bf = buffer_client()) {
            foreach (($bf->listProfiles() ?? []) as $p) {
                $out[] = [
                    'svc' => 'buffer',
                    'ref' => (string)($p['id'] ?? ''),
                    'platform' => buffer_map_platform((string)($p['service'] ?? '')),
                    'label' => (string)($p['formatted_username'] ?? ($p['service_username'] ?? '')),
                    'sel' => false,
                ];
            }
        }
    } catch (Throwable $e) { /* service offline */ }
    return $out;
}

/** Download Telegram media to a temp file. */
function tg_fetch_media(Telegram $tg, string $botToken, array $media): array {
    $fileInfo = $tg->request('getFile', null, ['file_id' => $media['fid']]);
    $filePath = (string)($fileInfo['file_path'] ?? '');
    if ($filePath === '') {
        throw new RuntimeException('Telegram returned no file path.');
    }
    $downloadUrl = 'https://api.telegram.org/file/bot' . $botToken . '/' . $filePath;
    $localTmp = tempnam(sys_get_temp_dir(), 'tgm');
    if (!@copy($downloadUrl, $localTmp)) {
        throw new RuntimeException('Could not download the media from Telegram.');
    }
    return [
        'tmp' => $localTmp,
        'name' => basename($filePath),
        'mime' => $media['mime'],
        'tg_url' => $downloadUrl,
    ];
}

/** Upload media to Zernio presigned storage; returns its public URL. */
function tg_upload_zernio(Zernio $zn, string $tmpPath, string $name, string $mime): string {
    $presign = $zn->presignMedia($name, $mime);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $presign['uploadUrl'],
        CURLOPT_PUT => 1,
        CURLOPT_INFILE => fopen($tmpPath, 'r'),
        CURLOPT_INFILESIZE => filesize($tmpPath),
        CURLOPT_HTTPHEADER => ["Content-Type: {$mime}"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($code >= 300) {
        throw new RuntimeException('Zernio upload failed (HTTP ' . $code . ')');
    }
    return (string)$presign['publicUrl'];
}

/* ============================ UI builders ================================= */

function kb_home(): array {
    return ['inline_keyboard' => [
        [
            ['text' => "\xF0\x9F\x93\x9D New post", 'callback_data' => 'menu:newpost'],
            ['text' => "\xF0\x9F\x93\x8A Analytics", 'callback_data' => 'menu:analytics'],
        ],
        [
            ['text' => "\xF0\x9F\x93\x8C Recent posts", 'callback_data' => 'menu:recent'],
            ['text' => "\xF0\x9F\x93\xA3 Channels", 'callback_data' => 'menu:channels'],
        ],
        [['text' => "\xE2\x9D\x93 Help", 'callback_data' => 'menu:help']],
    ]];
}

function view_home(): string {
    return "<b>\xF0\x9F\x9A\x80 Post Studio Bot</b>\n"
        . "Manage your social posting without leaving Telegram.\n\n"
        . "Pick an action below or type a command:";
}

function kb_cancel_only(): array {
    return ['inline_keyboard' => [[
        ['text' => "\xE2\x9D\x8C Cancel", 'callback_data' => 'cx'],
    ]]];
}

/* ------------------------ wizard view builders --------------------------- */

function kb_caption(bool $hasText, bool $hasMedia): array {
    $rows = [];
    $row = [];
    if ($hasText && !$hasMedia) {
        $row[] = ['text' => '📎 Send photo/video', 'callback_data' => 'noop'];
        $row[] = ['text' => '⏭ Skip media', 'callback_data' => 'sk:m'];
    }
    if ($hasMedia && !$hasText) {
        $row[] = ['text' => '⏭ Skip caption', 'callback_data' => 'sk:t'];
    }
    if ($row) $rows[] = $row;
    $rows[] = [['text' => '❌ Cancel', 'callback_data' => 'cx']];
    return ['inline_keyboard' => $rows];
}

function caption_progress(array $d): string {
    $t = (string)($d['text'] ?? '');
    $lines = [];
    $lines[] = $t !== ''
        ? '✅ <b>Caption:</b> ' . e(mb_substr($t, 0, 60)) . (mb_strlen($t) > 60 ? '…' : '')
        : '⬜️ <b>Caption:</b> not set';
    $lines[] = isset($d['media'])
        ? '✅ <b>Media:</b> ' . ($d['media']['type'] === 'video' ? '🎬 video' : '🖼 photo')
        : '⬜️ <b>Media:</b> none';
    return implode("\n", $lines);
}

function step_bar(string $n): string {
    return "<i>Step " . $n . " of 4</i>";
}

function kb_channels(array $chans): array {
    $rows = [];
    foreach ($chans as $i => $c) {
        $mark = !empty($c['sel']) ? '✅' : '⬜️';
        $rows[] = [[
            'text' => $mark . ' ' . svc_badge($c['svc']) . ' · ' . $c['platform'] . ' — ' . mb_substr($c['label'], 0, 24),
            'callback_data' => 'ct:' . $i,
        ]];
    }
    if ($rows) {
        $rows[] = [
            ['text' => '✅ Select all', 'callback_data' => 'ca:a'],
            ['text' => '⬜️ Clear', 'callback_data' => 'cn'],
        ];
        $rows[] = [
            ['text' => '➡️ Next', 'callback_data' => 'nx'],
            ['text' => '❌ Cancel', 'callback_data' => 'cx'],
        ];
    } else {
        $rows[] = [['text' => '🔄 Refresh', 'callback_data' => 'menu:newpost']];
        $rows[] = [['text' => '🏠 Menu', 'callback_data' => 'menu:home']];
    }
    return ['inline_keyboard' => $rows];
}

function view_channels_html(array $d): string {
    $chans = $d['channels'] ?? [];
    $sel = 0;
    foreach ($chans as $c) if (!empty($c['sel'])) $sel++;
    $html = "📢 <b>Select channels</b> — <i>" . $sel . "/" . count($chans) . " selected</i>\n\n";
    if (!$chans) {
        return $html . "⚠️ No connected channels found.\nConnect accounts in Post Studio first.";
    }
    $lastSvc = '';
    foreach ($chans as $c) {
        if ($c['svc'] !== $lastSvc) {
            $html .= "\n<b>" . svc_badge($c['svc']) . "</b>\n";
            $lastSvc = $c['svc'];
        }
        $mark = !empty($c['sel']) ? '✅' : '▫️';
        $html .= $mark . ' ' . e($c['platform']) . ' — <code>' . e($c['label']) . "</code>\n";
    }
    return $html;
}

function kb_schedule(): array {
    return ['inline_keyboard' => [
        [
            ['text' => '⚡ Publish now', 'callback_data' => 'sn'],
            ['text' => '🕐 Schedule…', 'callback_data' => 'sl'],
        ],
        [
            ['text' => '⬅️ Back', 'callback_data' => 'bk:c'],
            ['text' => '❌ Cancel', 'callback_data' => 'cx'],
        ],
    ]];
}

function kb_confirm(bool $isNow): array {
    return ['inline_keyboard' => [
        [['text' => $isNow ? '🚀 Publish now' : '🗓 Confirm schedule', 'callback_data' => 'go']],
        [
            ['text' => '⬅️ Back', 'callback_data' => 'bk:s'],
            ['text' => '❌ Cancel', 'callback_data' => 'cx'],
        ],
    ]];
}

function view_confirm_html(array $d): string {
    $isNow = empty($d['schedule']);
    $selChans = array_values(array_filter($d['channels'] ?? [], function ($c) { return !empty($c['sel']); }));
    $counts = ['zernio' => 0, 'bulkpublish' => 0, 'buffer' => 0];
    foreach ($selChans as $c) { $counts[$c['svc']]++; }

    $html = '📝 <b>Confirm your post</b>' . step_bar('4') . "\n\n";
    $t = (string)($d['text'] ?? '');
    $html .= "<b>Caption</b>\n" . ($t !== '' ? '<blockquote>' . e(mb_substr($t, 0, 300)) . (mb_strlen($t) > 300 ? '…' : '') . '</blockquote>' : "<i>(none)</i>") . "\n";
    $html .= '<b>Media</b>: ';
    $html .= isset($d['media']) ? ($d['media']['type'] === 'video' ? '🎬 video' : '🖼 photo') : '<i>(none)</i>';
    $html .= "\n<b>Channels</b>: " . count($selChans) . "\n";
    foreach ($counts as $svc => $n) {
        if ($n > 0) $html .= '• ' . svc_badge($svc) . ' × ' . $n . "\n";
    }
    $hasVideo = isset($d['media']) && $d['media']['type'] === 'video';
    if ($hasVideo && $counts['buffer'] > 0) {
        $html .= "\n⚠️ Buffer channels will be <b>skipped</b> (no video support).\n";
    }
    $html .= "\n<b>Schedule</b>: ";
    $html .= $isNow ? '⚡ Immediately' : "🕐 <code>" . e((string)$d['schedule']) . " UTC</code>";
    return $html;
}

/* --------------------------- publish engine ------------------------------ */

/** Map internal platform name onto BulkPublish slugs. */
function tg_bp_slug(string $platform): string {
    $map = ['twitter' => 'x', 'googlebusiness' => 'gmb'];
    return $map[$platform] ?? $platform;
}

/** Mirror a created post into the local posts table (non-fatal). */
function tg_mirror(array $post, string $service, string $content, string $mediaType, string $mediaJson, string $platformsJson, ?string $scheduledFor, string $status): void {
    try {
        $stmt = db()->prepare(
            'INSERT INTO posts (zernio_post_id, service, content, media_type, media_json, platforms_json, scheduled_for, timezone, status, source)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $post['_id'] ?? ($post['id'] ?? ''),
            $service,
            $content,
            $mediaType,
            $mediaJson,
            $platformsJson,
            $scheduledFor,
            'UTC',
            $status,
            'telegram',
        ]);
    } catch (Throwable $e) { /* non-fatal */ }
}

/**
 * Publish the confirmed draft to every selected service.
 * Returns an HTML results block.
 */
function tg_publish(Telegram $tg, string $chatId, array &$data): string {
    $text = (string)($data['text'] ?? '');
    $media = $data['media'] ?? null;
    $isNow = empty($data['schedule']);
    $schedLocal = $isNow ? null : (string)$data['schedule']; // UTC "Y-m-d H:i"
    $dtUtc = null;
    if (!$isNow) {
        $dtUtc = DateTime::createFromFormat('Y-m-d H:i', $schedLocal, new DateTimeZone('UTC'));
        if (!$dtUtc) {
            return "❌ Invalid schedule time stored. Start over with /post";
        }
    }

    $selected = array_values(array_filter($data['channels'] ?? [], function ($c) { return !empty($c['sel']); }));
    $bySvc = ['zernio' => [], 'bulkpublish' => [], 'buffer' => []];
    foreach ($selected as $c) { $bySvc[$c['svc']][] = $c; }

    $out = [];
    $mediaType = $media ? ($media['type'] === 'video' ? 'video' : 'image') : 'caption';

    /* --- prepare media once (needed by ZN/BP uploads and Buffer URL) --- */
    $botToken = (string)get_setting('telegram_bot_token', '');
    $tmpInfo = null;   // ['tmp','name','mime','tg_url']
    $zUrl = null;      // zernio public url
    $bpId = null;      // bulkpublish file id
    try {
        if ($media) {
            if (($bySvc['zernio'] || $bySvc['bulkpublish']) && $botToken !== '') {
                $tg->sendChatAction($chatId, $media['type'] === 'video' ? 'upload_video' : 'upload_photo');
                $tmpInfo = tg_fetch_media($tg, $botToken, $media);
            } else {
                // Buffer-only: resolve public URL without downloading.
                $fi = $tg->request('getFile', null, ['file_id' => $media['fid']]);
                $fp = (string)($fi['file_path'] ?? '');
                if ($fp !== '') $tmpInfo = ['tmp' => null, 'name' => basename($fp), 'mime' => $media['mime'], 'tg_url' => 'https://api.telegram.org/file/bot' . $botToken . '/' . $fp];
            }
            if ($tmpInfo && $bySvc['zernio']) {
                $znTmp = zernio_client();
                if ($znTmp) {
                    $zUrl = tg_upload_zernio($znTmp, $tmpInfo['tmp'], $tmpInfo['name'], $tmpInfo['mime']);
                }
            }
            if ($tmpInfo && $tmpInfo['tmp'] && $bySvc['bulkpublish']) {
                $bpTmp = bulkpublish_client();
                if ($bpTmp) {
                    $up = $bpTmp->uploadMedia($tmpInfo['tmp'], $tmpInfo['name'], $tmpInfo['mime']);
                    if (!empty($up['id'])) $bpId = (int)$up['id'];
                }
            }
        }
    } catch (Throwable $e) {
        $out[] = "⚠️ Media transfer failed: " . e($e->getMessage());
    }

    $platformsJson = json_encode(array_map(function ($c) {
        return ['service' => $c['svc'], 'platform' => $c['platform'], 'label' => $c['label']];
    }, $selected));
    $mediaJson = json_encode($media ? [$media] : []);
    $isoUtc = (!$isNow && $dtUtc) ? $dtUtc->format('Y-m-d\TH:i:s\Z') : null;
    $sqlSched = (!$isNow && $dtUtc) ? $dtUtc->format('Y-m-d H:i:s') : null;

    /* --- Zernio --- */
    if ($bySvc['zernio']) {
        try {
            $zn = zernio_client();
            if (!$zn) throw new RuntimeException('No Zernio API key configured.');
            $payload = ['content' => $text, 'publishNow' => $isNow, 'platforms' => []];
            foreach ($bySvc['zernio'] as $c) {
                $payload['platforms'][] = ['platform' => $c['platform'], 'accountId' => $c['ref']];
            }
            if ($zUrl) $payload['mediaItems'] = [['url' => $zUrl, 'type' => $mediaType]];
            if (!$isNow) {
                $payload['scheduledFor'] = substr($isoUtc, 0, 19);
                $payload['timezone'] = 'UTC';
            }
            $res = $zn->createPost($payload);
            $post = is_array($res['post'] ?? null) ? $res['post'] : [];
            tg_mirror($post, 'zernio', $text, $mediaType, $mediaJson, $platformsJson, $sqlSched, (string)($post['status'] ?? ($isNow ? 'publishing' : 'scheduled')));
            $out[] = "🟣 <b>Zernio</b>: ✅ sent to " . count($bySvc['zernio']) . " channel(s)" . (empty($post['_id']) ? '' : " — <code>" . e((string)$post['_id']) . "</code>");
        } catch (Throwable $e) {
            $out[] = "🟣 <b>Zernio</b>: ❌ " . e($e->getMessage());
        }
    }

    /* --- BulkPublish --- */
    if ($bySvc['bulkpublish']) {
        try {
            $bp = bulkpublish_client();
            if (!$bp) throw new RuntimeException('No BulkPublish API key configured.');
            $payload = ['content' => $text, 'channels' => []];
            foreach ($bySvc['bulkpublish'] as $c) {
                $payload['channels'][] = ['channelId' => (int)$c['ref'], 'platform' => tg_bp_slug($c['platform'])];
            }
            if ($bpId) $payload['mediaFiles'] = [$bpId];
            if ($isNow) {
                $payload['status'] = 'draft';
            } else {
                $payload['status'] = 'scheduled';
                $payload['scheduledAt'] = $isoUtc;
                $payload['timezone'] = 'UTC';
            }
            $post = $bp->createPost($payload);
            $pid = isset($post['id']) ? (string)$post['id'] : '';
            if ($isNow && $pid !== '') {
                $bp->publishPost((int)$pid);
            }
            tg_mirror(['id' => $pid], 'bulkpublish', $text, $mediaType, $mediaJson, $platformsJson, $sqlSched, $isNow ? 'publishing' : 'scheduled');
            $out[] = "🔵 <b>BulkPublish</b>: ✅ sent to " . count($bySvc['bulkpublish']) . " channel(s)" . ($pid !== '' ? " — <code>" . e($pid) . "</code>" : '');
        } catch (Throwable $e) {
            $out[] = "🔵 <b>BulkPublish</b>: ❌ " . e($e->getMessage());
        }
    }

    /* --- Buffer --- */
    if ($bySvc['buffer']) {
        try {
            $bf = buffer_client();
            if (!$bf) throw new RuntimeException('No Buffer token configured.');
            if (trim($text) === '') throw new RuntimeException('Buffer requires a caption.');
            if ($mediaType === 'video') throw new RuntimeException('Video not supported by Buffer classic API.');
            $profileIds = array_map(function ($c) { return (string)$c['ref']; }, $bySvc['buffer']);
            $opts = [];
            if ($isNow) {
                $opts['now'] = true;
            } else {
                $opts['scheduledAt'] = $isoUtc;
            }
            if (!$zUrl && $tmpInfo && $mediaType === 'image') {
                $opts['photoUrl'] = $tmpInfo['tg_url'];
            } elseif ($zUrl && $mediaType === 'image') {
                $opts['photoUrl'] = $zUrl;
            }
            $resp = $bf->createUpdate($profileIds, $text, $opts);
            $updates = $resp['updates'] ?? [];
            if (!$updates) throw new RuntimeException('Buffer returned no updates.');
            tg_mirror(['_id' => (string)($updates[0]['id'] ?? '')], 'buffer', $text, $mediaType, $mediaJson, $platformsJson, $sqlSched, buffer_map_status((string)($updates[0]['status'] ?? '')));
            $out[] = "🟢 <b>Buffer</b>: ✅ queued to " . count($updates) . " profile(s)";
        } catch (Throwable $e) {
            $out[] = "🟢 <b>Buffer</b>: ❌ " . e($e->getMessage());
        }
    }

    if ($tmpInfo && !empty($tmpInfo['tmp'])) @unlink($tmpInfo['tmp']);

    return "📣 <b>Publishing results</b>\n\n" . implode("\n", $out);
}

/* --------------------------- data views ---------------------------------- */

function view_analytics(): string {
    require_once __DIR__ . '/../includes/metrics.php';
    $published = 0; $failed = 0; $scheduled = 0; $reach = 0; $reachKnown = false;
    try {
        if ($zn = zernio_client()) {
            foreach (($zn->listPosts(['limit' => 100])['posts'] ?? []) as $p) {
                $st = strtolower((string)($p['status'] ?? ''));
                if ($st === 'published') $published++;
                elseif ($st === 'failed') $failed++;
                elseif ($st === 'scheduled' || $st === 'publishing') $scheduled++;
                $pm = ps_extract_metrics($p);
                if ($pm['found']) { $reach += $pm['views']; $reachKnown = true; }
            }
        }
    } catch (Throwable $e) { /* service offline */ }
    try {
        if ($bp = bulkpublish_client()) {
            foreach (($bp->listPosts(['limit' => 100])['posts'] ?? []) as $p) {
                $st = strtolower((string)($p['status'] ?? ''));
                if ($st === 'published') $published++;
                elseif ($st === 'failed') $failed++;
                elseif ($st === 'scheduled' || $st === 'processing') $scheduled++;
                $pm = ps_extract_metrics($p);
                if ($pm['found']) { $reach += $pm['views']; $reachKnown = true; }
            }
        }
    } catch (Throwable $e) { /* service offline */ }

    $html = "📊 <b>Analytics overview</b>\n\n";
    $html .= "✅ Published: <b>" . number_format($published) . "</b>\n";
    $html .= "🕐 Scheduled: <b>" . number_format($scheduled) . "</b>\n";
    $html .= "❌ Failed: <b>" . number_format($failed) . "</b>\n";
    if ($reachKnown) {
        $html .= "👁 Reach: <b>" . number_format($reach) . "</b>\n";
    }
    try {
        require_once __DIR__ . '/../includes/db.php';
        $views = get_engagement_views();
        if ($views > 0) {
            $html .= "👀 Post views in app: <b>" . number_format($views) . "</b>\n";
        }
    } catch (Throwable $e) { /* ignore */ }
    return $html;
}

function view_recent(): string {
    $items = [];
    try {
        if ($zn = zernio_client()) {
            foreach (($zn->listPosts(['limit' => 5])['posts'] ?? []) as $p) {
                $ts = strtotime((string)($p['createdAt'] ?? ($p['scheduledFor'] ?? ''))) ?: 0;
                $items[] = ['ts' => $ts, 'line' => status_emoji((string)($p['status'] ?? '')) . " <b>ZN</b> · " . e(mb_substr(trim((string)($p['content'] ?? '(no text)')), 0, 40))];
            }
        }
    } catch (Throwable $e) { /* ignore */ }
    try {
        if ($bp = bulkpublish_client()) {
            foreach (($bp->listPosts(['limit' => 5])['posts'] ?? []) as $p) {
                $ts = strtotime((string)($p['createdAt'] ?? ($p['scheduledAt'] ?? ''))) ?: 0;
                $items[] = ['ts' => $ts, 'line' => status_emoji((string)($p['status'] ?? '')) . " <b>BP</b> · " . e(mb_substr(trim((string)($p['content'] ?? '(no text)')), 0, 40))];
            }
        }
    } catch (Throwable $e) { /* ignore */ }
    usort($items, function ($a, $b) { return $b['ts'] <=> $a['ts']; });
    $items = array_slice($items, 0, 5);
    if (!$items) return "📌 <b>Recent posts</b>\n\nNo posts found yet.";
    $lines = [];
    foreach ($items as $i => $it) $lines[] = ($i + 1) . ". " . $it['line'];
    return "📌 <b>Recent posts</b>\n\n" . implode("\n", $lines);
}

function view_channels_list(): string {
    $chans = tg_collect_channels();
    if (!$chans) return "📣 <b>Channels</b>\n\n⚠️ No connected channels. Add API keys in Post Studio → Settings.";
    $groups = [];
    foreach ($chans as $c) $groups[$c['svc']][] = $c;
    $html = "📣 <b>Connected channels</b> (" . count($chans) . ")\n";
    foreach (['zernio', 'bulkpublish', 'buffer'] as $svc) {
        if (empty($groups[$svc])) continue;
        $html .= "\n<b>" . svc_badge($svc) . "</b> — " . count($groups[$svc]) . "\n";
        foreach ($groups[$svc] as $c) {
            $html .= "• " . e($c['platform']) . " — <code>" . e($c['label']) . "</code>\n";
        }
    }
    return $html;
}

function view_help(): string {
    return "🤖 <b>Post Studio Bot — Guide</b>\n\n"
        . "<b>📝 Create a post</b>\n"
        . "Tap <b>New post</b>, then follow the 4 steps:\n"
        . "1️⃣ Send a caption and/or a photo / video\n"
        . "2️⃣ Tap channels to select them ✅\n"
        . "3️⃣ Choose publish now or schedule (UTC)\n"
        . "4️⃣ Review and confirm 🚀\n\n"
        . "<b>Commands</b>\n"
        . "/post — start the post wizard\n"
        . "/analytics — published / failed / reach\n"
        . "/recent — last 5 posts\n"
        . "/channels — list connected channels\n"
        . "/cancel — abort current draft\n"
        . "/help — this guide\n\n"
        . "<b>Tips</b>\n"
        . "• You can send media first, caption later\n"
        . "• Scheduling uses UTC time (YYYY-MM-DD HH:MM)\n"
        . "• Buffer supports one image per post, no video";
}

/* ----------------------------- routing ----------------------------------- */

$raw = file_get_contents('php://input');
$update = json_decode($raw, true);

function finish_ok(): void {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    $GLOBALS['__tg_done'] = true;
    echo '{"ok":true}';
    exit;
}

if (!is_array($update)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid update']);
    exit;
}

$botToken = (string)get_setting('telegram_bot_token', '');
if ($botToken === '') {
    finish_ok();
}

$telegram = new Telegram($botToken);

/** Edit an existing bot message, falling back to sending a new one. */
function tg_render(Telegram $tg, $chatId, int $msgId, string $html, array $kb): void {
    try {
        $tg->editMessageText((string)$chatId, $msgId, $html, 'HTML', $kb);
    } catch (Throwable $e) {
        try { $tg->sendMessage((string)$chatId, $html, 'HTML', $kb); } catch (Throwable $e2) { /* ignore */ }
    }
}

try {
    /* ---------- callback query branch ---------- */
    if (isset($update['callback_query'])) {
        $cq = $update['callback_query'];
        $chatId = $cq['message']['chat']['id'] ?? 0;
        $msgId = (int)($cq['message']['message_id'] ?? 0);
        $cbData = (string)($cq['data'] ?? '');
        $cbId = (string)($cq['id'] ?? '');
        if ($chatId === 0) finish_ok();

        if (!telegram_chat_allowed($chatId)) {
            $telegram->answerCallbackQuery($cbId, '🔒 This bot is private. Use /pair CODE', true);
            finish_ok();
        }

        $session = tg_load_session($chatId);
        $state = $session['state'];
        $data = $session['data'];
        $answered = false;

        if (starts_with($cbData, 'menu:')) {
            $view = substr($cbData, 5);
            if ($view === 'newpost') {
                tg_clear_session($chatId);
                try {
                    $m = $telegram->sendMessage((string)$chatId,
                        "📝 <b>New post</b> — " . step_bar('1') . "\n\n"
                        . "Send me the <b>caption text</b> now — or just send a photo/video and I'll ask for the caption after.\n\n"
                        . "<i>Type /cancel anytime to abort.</i>", 'HTML', kb_caption(false, false));
                    tg_save_session($chatId, 'caption', ['mid' => $m['message_id']]);
                } catch (Throwable $e) { /* ignore */ }
            } else {
                $html = view_home(); $kb = kb_home();
                if ($view === 'analytics') { $html = view_analytics(); }
                elseif ($view === 'recent') { $html = view_recent(); }
                elseif ($view === 'channels') { $html = view_channels_list(); }
                elseif ($view === 'help') { $html = view_help(); }
                if ($view !== 'home') { $kb = ['inline_keyboard' => [[['text' => '🏠 Menu', 'callback_data' => 'menu:home']]]]; }
                tg_render($telegram, $chatId, $msgId, $html, $kb);
            }
            $telegram->answerCallbackQuery($cbId);
            finish_ok();
        }

        /* skip media / skip caption (caption state) */
        if (starts_with($cbData, 'sk:') && $state === 'caption') {
            if ($cbData === 'sk:t') $data['text'] = '';
            if (isset($data['text']) || isset($data['media'])) {
                tg_save_session($chatId, 'channels', $data);
                $data['channels'] = tg_collect_channels();
                tg_save_session($chatId, 'channels', $data);
                tg_render($telegram, $chatId, $msgId, view_channels_html($data), kb_channels($data['channels']));
            } else {
                tg_render($telegram, $chatId, $msgId,
                    "📝 <b>New post</b> — " . step_bar('1') . "\n\n"
                    . "Send me the <b>caption text</b> or a photo/video.",
                    kb_caption(false, false));
            }
            $telegram->answerCallbackQuery($cbId);
            finish_ok();
        }

        if (starts_with($cbData, 'ct:') && $state === 'channels') {
            $i = (int)substr($cbData, 3);
            if (isset($data['channels'][$i])) {
                $data['channels'][$i]['sel'] = empty($data['channels'][$i]['sel']);
                tg_save_session($chatId, 'channels', $data);
                tg_render($telegram, $chatId, $msgId, view_channels_html($data), kb_channels($data['channels']));
            }
            $telegram->answerCallbackQuery($cbId);
            $answered = true;
        }

        if (($cbData === 'ca:a' || $cbData === 'cn') && $state === 'channels') {
            foreach (($data['channels'] ?? []) as $i => $c) {
                $data['channels'][$i]['sel'] = ($cbData === 'ca:a');
            }
            tg_save_session($chatId, 'channels', $data);
            tg_render($telegram, $chatId, $msgId, view_channels_html($data), kb_channels($data['channels']));
            $telegram->answerCallbackQuery($cbId);
            $answered = true;
        }

        if ($cbData === 'nx' && $state === 'channels') {
            $selCount = count(array_filter($data['channels'] ?? [], function ($c) { return !empty($c['sel']); }));
            if ($selCount === 0) {
                $telegram->answerCallbackQuery($cbId, 'Select at least one channel first', true);
            } else {
                tg_save_session($chatId, 'schedule', $data);
                tg_render($telegram, $chatId, $msgId,
                    "🕒 <b>Schedule</b> — " . step_bar('3') . "\n\nWhen should this go out?",
                    kb_schedule());
                $telegram->answerCallbackQuery($cbId);
            }
            $answered = true;
        }

        if ($cbData === 'sn' && $state === 'schedule') {
            unset($data['schedule']);
            tg_save_session($chatId, 'confirm', $data);
            tg_render($telegram, $chatId, $msgId, view_confirm_html($data), kb_confirm(true));
            $telegram->answerCallbackQuery($cbId);
            $answered = true;
        }

        if ($cbData === 'sl' && $state === 'schedule') {
            tg_save_session($chatId, 'sched_time', $data);
            tg_render($telegram, $chatId, $msgId,
                "🕐 <b>Schedule</b> — send date &amp; time in <b>UTC</b>:\n<code>YYYY-MM-DD HH:MM</code>\n\nExample: <code>" . date('Y-m-d H:i', time() + 3600) . "</code>",
            kb_cancel_only());
            $telegram->answerCallbackQuery($cbId);
            $answered = true;
        }

        if ($cbData === 'bk:c' && ($state === 'schedule')) {
            tg_save_session($chatId, 'channels', $data);
            tg_render($telegram, $chatId, $msgId, view_channels_html($data), kb_channels($data['channels']));
            $telegram->answerCallbackQuery($cbId);
            $answered = true;
        }

        if ($cbData === 'bk:s' && $state === 'confirm') {
            tg_save_session($chatId, 'schedule', $data);
            tg_render($telegram, $chatId, $msgId, "🕒 <b>Schedule</b> — " . step_bar('3') . "\n\nWhen should this go out?", kb_schedule());
            $telegram->answerCallbackQuery($cbId);
            $answered = true;
        }

        if ($cbData === 'go' && $state === 'confirm') {
            $telegram->answerCallbackQuery($cbId);
            $tgMsg = "⏳ Publishing… one moment.";
            tg_render($telegram, $chatId, $msgId, $tgMsg, []);
            $resultHtml = tg_publish($telegram, $chatId, $data);
            tg_clear_session($chatId);
            try {
                $telegram->sendMessage((string)$chatId, $resultHtml . "\n\n🏠 Use /post for another one.", 'HTML', kb_home());
            } catch (Throwable $e) { /* ignore */ }
            $answered = true;
        }

        if ($cbData === 'cx') {
            tg_clear_session($chatId);
            tg_render($telegram, $chatId, $msgId, view_home(), kb_home());
            $telegram->answerCallbackQuery($cbId, 'Draft cancelled');
            $answered = true;
        }

        if (!$answered) {
            $telegram->answerCallbackQuery($cbId);
        }
        finish_ok();
    }

    /* ---------- message branch ---------- */
    $message = $update['message'] ?? [];
    $chatId = $message['chat']['id'] ?? 0;
    if ($chatId === 0) finish_ok();

    $text = trim((string)($message['text'] ?? ''));
    $photo = $message['photo'] ?? null;
    $video = $message['video'] ?? null;
    $document = $message['document'] ?? null;
    $captionText = trim((string)($message['caption'] ?? ''));

    /* ---------- pairing / auth gate ---------- */
    if (!telegram_chat_allowed($chatId)) {
        if (starts_with($text, '/pair')) {
            $code = trim(substr($text, 5));
            if ($code !== '' && telegram_pair_code_valid($code)) {
                $chatTitle = trim(($message['chat']['first_name'] ?? '') . ' ' . ($message['chat']['last_name'] ?? ''));
                if ($chatTitle === '') $chatTitle = (string)($message['chat']['title'] ?? ('Chat ' . $chatId));
                telegram_allow_chat($chatId, $chatTitle, (string)($message['chat']['username'] ?? ''));
                set_setting('telegram_pair_code', '');
                set_setting('telegram_pair_expires', '');
                $telegram->sendMessage((string)$chatId, "🎉 <b>Paired!</b> This chat can now control Post Studio.\n\n" . view_home(), 'HTML', kb_home());
            } else {
                $telegram->sendMessage((string)$chatId,
                    "🔒 <b>Invalid or expired code.</b>\nOpen Post Studio → <b>Telegram Bot</b> page, generate a fresh pairing code, then send:\n<code>/pair 123456</code>",
                'HTML');
            }
        } else {
            $telegram->sendMessage((string)$chatId,
                "🔒 <b>This bot is private.</b>\nTo use it, open your Post Studio dashboard → <b>Telegram Bot</b> → generate a pairing code, then send me:\n<code>/pair 123456</code>",
            'HTML');
        }
        finish_ok();
    }

    /* ---------- global commands ---------- */
    if ($text !== '' && starts_with($text, '/')) {
        $cmd = strtolower(preg_replace('/\s.*$/', '', $text));
        if ($cmd === '/start' || $cmd === '/menu' || $cmd === '/home') {
            tg_clear_session($chatId);
            $telegram->sendMessage((string)$chatId, view_home(), 'HTML', kb_home());
            finish_ok();
        }
        if ($cmd === '/cancel') {
            tg_clear_session($chatId);
            $telegram->sendMessage((string)$chatId, "🗑 Draft discarded.", 'HTML', kb_home());
            finish_ok();
        }
        if ($cmd === '/post' || $cmd === '/newpost') {
            tg_save_session($chatId, 'caption', []);
            $telegram->sendMessage((string)$chatId,
                "📝 <b>New post</b> — " . step_bar('1') . "\n\n"
                . "Send me the <b>caption text</b> — or just send a photo/video and I'll ask for the caption after.",
            'HTML', kb_caption(false, false));
            finish_ok();
        }
        if ($cmd === '/analytics') {
            $telegram->sendMessage((string)$chatId, view_analytics(), 'HTML', ['inline_keyboard' => [[['text' => '🏠 Menu', 'callback_data' => 'menu:home']]]]);
            finish_ok();
        }
        if ($cmd === '/recent' || $cmd === '/status') {
            $telegram->sendMessage((string)$chatId, view_recent(), 'HTML');
            finish_ok();
        }
        if ($cmd === '/channels') {
            $telegram->sendMessage((string)$chatId, view_channels_list(), 'HTML');
            finish_ok();
        }
        if ($cmd === '/help') {
            $telegram->sendMessage((string)$chatId, view_help(), 'HTML');
            finish_ok();
        }
        if ($cmd === '/pair') {
            $telegram->sendMessage((string)$chatId, "✅ This chat is already paired.", 'HTML');
            finish_ok();
        }
        // Unknown command
        $telegram->sendMessage((string)$chatId,
            "❓ Unknown command <code>" . e($cmd) . "</code>. Here's what I can do:", 'HTML', kb_home());
        finish_ok();
    }

    /* ---------- stateful input ---------- */
    $session = tg_load_session($chatId);
    $state = $session['state'];
    $data = $session['data'];

    // Extract incoming media, if any.
    $incomingMedia = null;
    $incomingSize = 0;
    if (is_array($photo)) {
        $lastPhoto = end($photo);
        $incomingMedia = ['fid' => (string)$lastPhoto['file_id'], 'type' => 'image', 'mime' => 'image/jpeg'];
        $incomingSize = (int)($lastPhoto['file_size'] ?? 0);
    } elseif (is_array($video)) {
        $incomingMedia = ['fid' => (string)$video['file_id'], 'type' => 'video', 'mime' => (string)($video['mime_type'] ?? 'video/mp4')];
        $incomingSize = (int)($video['file_size'] ?? 0);
    } elseif (is_array($document)) {
        $mime = (string)($document['mime_type'] ?? '');
        if (strpos($mime, 'image') === 0) {
            $incomingMedia = ['fid' => (string)$document['file_id'], 'type' => 'image', 'mime' => $mime];
            $incomingSize = (int)($document['file_size'] ?? 0);
        } elseif (strpos($mime, 'video') === 0) {
            $incomingMedia = ['fid' => (string)$document['file_id'], 'type' => 'video', 'mime' => $mime];
            $incomingSize = (int)($document['file_size'] ?? 0);
        } else {
            $telegram->sendMessage((string)$chatId, "⚠️ Unsupported file type. Send a photo or video.");
            finish_ok();
        }
    }

    // Bots can only download files up to 20 MB from Telegram's servers.
    if ($incomingMedia && $incomingSize > 20 * 1024 * 1024) {
        $mb = round($incomingSize / 1048576, 1);
        $telegram->sendMessage((string)$chatId,
            "⚠️ That file is <b>" . $mb . " MB</b>, but Telegram only lets bots download files up to <b>20 MB</b>.\n\n"
            . "Send a smaller photo/video, or publish this one through the Post Studio web composer.",
            'HTML');
        finish_ok();
    }

    if ($state === 'caption') {
        if ($incomingMedia) $data['media'] = $incomingMedia;
        if ($text !== '') $data['text'] = $text;
        if ($captionText !== '' && $incomingMedia && !isset($data['text'])) $data['text'] = $captionText;

        if (isset($data['text']) && isset($data['media'])) {
            tg_save_session($chatId, 'channels', $data);
            $data['channels'] = tg_collect_channels();
            tg_save_session($chatId, 'channels', $data);
            $telegram->sendMessage((string)$chatId, "👍 Got it!\n\n" . view_channels_html($data), 'HTML', kb_channels($data['channels']));
        } elseif (isset($data['media'])) {
            tg_save_session($chatId, 'caption', $data);
            $telegram->sendMessage((string)$chatId,
                "🎬 Media received!\n\n" . caption_progress($data) . "\n\nNow type the <b>caption text</b> (or tap Skip).",
            'HTML', kb_caption(false, true));
        } elseif (isset($data['text'])) {
            tg_save_session($chatId, 'caption', $data);
            $telegram->sendMessage((string)$chatId,
                "✍️ Caption saved.\n\n" . caption_progress($data) . "\n\nOptionally send a <b>photo or video</b> now.",
            'HTML', kb_caption(true, false));
        } else {
            $telegram->sendMessage((string)$chatId,
                "📝 Send me the <b>caption text</b> or a photo/video to start.",
            'HTML', kb_caption(false, false));
        }
        finish_ok();
    }

    if ($state === 'sched_time') {
        $dt = DateTime::createFromFormat('Y-m-d H:i', $text, new DateTimeZone('UTC'));
        if (!$dt || $dt->format('Y-m-d H:i') !== $text) {
            $telegram->sendMessage((string)$chatId,
                "⚠️ Couldn't read that. Send the time as:\n<code>YYYY-MM-DD HH:MM</code> (UTC)",
            'HTML', kb_cancel_only());
            finish_ok();
        }
        $data['schedule'] = $dt->format('Y-m-d H:i');
        tg_save_session($chatId, 'confirm', $data);
        $telegram->sendMessage((string)$chatId, view_confirm_html($data), 'HTML', kb_confirm(false));
        finish_ok();
    }

    if (in_array($state, ['channels', 'schedule', 'confirm'], true)) {
        $telegram->sendMessage((string)$chatId,
            "✋ A draft is already in progress — use the buttons above, or /cancel to discard it first.",
        'HTML');
        finish_ok();
    }

    // Idle: friendly hint.
    if ($incomingMedia) {
        tg_save_session($chatId, 'caption', ['media' => $incomingMedia]);
        $telegram->sendMessage((string)$chatId,
            "🎬 Nice! " . caption_progress(['media' => $incomingMedia]) . "\n\nNow send the <b>caption text</b>, or tap Skip.",
        'HTML', kb_caption(false, true));
    } else {
        $telegram->sendMessage((string)$chatId,
            "👋 Hi! To publish something, tap <b>New post</b> below or send /post",
        'HTML', kb_home());
    }
    finish_ok();

} catch (Throwable $e) {
    try {
        $telegram->sendMessage((string)($chatId ?? 0), "⚠️ Unexpected error: " . e($e->getMessage()));
    } catch (Throwable $ignored) { /* ignore */ }
}

finish_ok();


<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/zernio.php';
require_once __DIR__ . '/../includes/bulkpublish.php';
require_once __DIR__ . '/../includes/buffer.php';
require_once __DIR__ . '/../includes/db.php';
require_login_ajax();

header('Content-Type: application/json');

$zernio = zernio_client();
$bp = bulkpublish_client();
$buf = buffer_client();
if (!$zernio && !$bp && !$buf) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No API key configured. Add a Zernio, BulkPublish or Buffer key in Settings.']);
    exit;
}

$dryRun = isset($_POST['dryRun']) && $_POST['dryRun'] === '1';
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please upload a CSV file.']);
    exit;
}

$filePath = $_FILES['file']['tmp_name'];

// Load accounts for lookup (both services).
$zernioLookup = [];
$bpLookup = [];
if ($zernio) {
    try {
        foreach (($zernio->listAccounts('', 'connected')['accounts'] ?? []) as $a) {
            $key = strtolower(trim($a['platform'] . '|' . ($a['username'] ?? '')));
            $zernioLookup[$key] = $a;
        }
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Failed to load Zernio accounts: ' . $e->getMessage()]);
        exit;
    }
}
if ($bp) {
    try {
        foreach (($bp->listChannels()['channels'] ?? []) as $c) {
            $platform = bp_map_platform((string)($c['platform'] ?? ''));
            $nameKey = strtolower(trim($platform . '|' . ($c['accountName'] ?? '')));
            $idKey = strtolower(trim($platform . '|' . ($c['accountId'] ?? '')));
            $bpLookup[$nameKey] = $c;
            if (!isset($bpLookup[$idKey])) $bpLookup[$idKey] = $c;
        }
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Failed to load BulkPublish channels: ' . $e->getMessage()]);
        exit;
    }
}

$bufferLookup = [];
if ($buf) {
    try {
        foreach (($buf->listProfiles()['profiles'] ?? []) as $p) {
            $platform = buffer_map_platform((string)($p['service'] ?? ''));
            $nameKey = strtolower(trim($platform . '|' . ($p['formatted_username'] ?? ($p['service_username'] ?? ''))));
            $idKey = strtolower(trim($platform . '|' . ($p['service_username'] ?? '')));
            $bufferLookup[$nameKey] = $p;
            if (!isset($bufferLookup[$idKey])) $bufferLookup[$idKey] = $p;
        }
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Failed to load Buffer profiles: ' . $e->getMessage()]);
        exit;
    }
}

// Parse CSV.
$rows = [];
$handle = fopen($filePath, 'r');
if (!$handle) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Could not read the uploaded file.']);
    exit;
}

$header = fgetcsv($handle);
if ($header === false) {
    fclose($handle);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'CSV is empty.']);
    exit;
}
// Strip UTF-8 BOM from the first header cell.
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
$header = array_map(function ($h) { return strtolower(trim($h)); }, $header);

$required = ['platform', 'username'];
foreach ($required as $col) {
    if (!in_array($col, $header, true)) {
        fclose($handle);
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => "CSV is missing the required column: $col"]);
        exit;
    }
}

$idx = array_flip($header);
$rowNum = 0;
while (($line = fgetcsv($handle)) !== false) {
    $rowNum++;
    if (count(array_filter($line, function ($v) { return trim((string)$v) !== ''; })) === 0) continue; // skip blank lines
    $rows[] = ['index' => $rowNum + 1, 'values' => $line];
}
fclose($handle);

$results = [];
foreach ($rows as $r) {
    $vals = $r['values'];
    $get = function ($col) use ($vals, $idx) {
        if (!isset($idx[$col])) return '';
        $i = $idx[$col];
        return trim((string)($vals[$i] ?? ''));
    };

    $svcRaw = strtolower($get('service'));
    $service = $svcRaw === 'bulkpublish' ? 'bulkpublish' : ($svcRaw === 'buffer' ? 'buffer' : 'zernio');
    $platform = strtolower($get('platform'));
    $username = $get('username');
    $caption = $get('caption');
    $mediaUrl = $get('media_url');
    $mediaType = strtolower($get('media_type')) ?: ($mediaUrl ? 'image' : 'caption');
    $scheduled = $get('scheduled_for');
    $timezone = $get('timezone') ?: 'UTC';
    $customContent = $get('custom_content');

    $errors = [];

    if ($platform === '' || $username === '') {
        $errors[] = 'platform and username are required';
    } elseif ($service === 'bulkpublish' && !$bp) {
        $errors[] = 'bulkpublish service selected but no BulkPublish API key configured';
    } elseif ($service === 'buffer' && !$buf) {
        $errors[] = 'buffer service selected but no Buffer access token configured';
    } elseif ($service === 'zernio' && !$zernio) {
        $errors[] = 'zernio service selected but no Zernio API key configured';
    }

    if ($mediaType !== 'caption' && $mediaUrl === '') {
        $errors[] = 'media_url required when media_type is image/video';
    }
    if ($mediaType !== 'caption' && !preg_match('#^https?://#i', $mediaUrl)) {
        $errors[] = 'media_url must be a public http(s) URL';
    }

    $scheduledFor = null;
    $scheduledTs = null;
    if ($scheduled !== '') {
        $ts = strtotime($scheduled);
        if ($ts === false) {
            $errors[] = 'invalid scheduled_for (use YYYY-MM-DD HH:MM)';
        } else {
            $scheduledFor = date('Y-m-d\TH:i:s', $ts);
            $scheduledTs = $ts;
        }
    }

    if (count($errors) === 0) {
        if ($service === 'bulkpublish') {
            $res = bulkpublish_row($bp, $bpLookup, $platform, $username, $caption, $mediaUrl, $mediaType, $scheduledFor, $scheduledTs, $timezone, $customContent, $dryRun);
        } elseif ($service === 'buffer') {
            $res = buffer_row($buf, $bufferLookup, $platform, $username, $caption, $mediaUrl, $mediaType, $scheduledFor, $scheduledTs, $timezone, $customContent, $dryRun);
        } else {
            $res = zernio_row($zernio, $zernioLookup, $platform, $username, $caption, $mediaUrl, $mediaType, $scheduledFor, $scheduledTs, $timezone, $customContent, $dryRun);
        }
        $res['rowIndex'] = $r['index'];
        $results[] = $res;
    } else {
        $results[] = ['rowIndex' => $r['index'], 'ok' => false, 'errors' => $errors];
    }
}

$valid = 0;
$invalid = 0;
foreach ($results as $res) {
    if ($res['ok']) $valid++; else $invalid++;
}

http_response_code(($valid > 0 && $invalid > 0) ? 207 : 200);
echo json_encode([
    'ok' => true,
    'total' => count($results),
    'valid' => $valid,
    'invalid' => $invalid,
    'results' => $results,
    'warnings' => [],
    'dryRun' => $dryRun,
]);

function zernio_row($zernio, $zernioLookup, $platform, $username, $caption, $mediaUrl, $mediaType, $scheduledFor, $scheduledTs, $timezone, $customContent, $dryRun) {
    $lookupKey = strtolower($platform . '|' . $username);
    $account = $zernioLookup[$lookupKey] ?? null;
    if ($account === null) {
        return ['ok' => false, 'errors' => ["no connected Zernio account for platform:$platform username:$username"]];
    }
    $entry = ['platform' => $account['platform'], 'accountId' => $account['_id']];
    if ($customContent !== '') $entry['customContent'] = $customContent;
    $payload = ['platforms' => [$entry]];
    if ($caption !== '') $payload['content'] = $caption;
    if ($mediaType !== 'caption') $payload['mediaItems'] = [['url' => $mediaUrl, 'type' => $mediaType]];
    if ($scheduledFor) {
        $payload['scheduledFor'] = $scheduledFor;
        $payload['timezone'] = $timezone;
    } else {
        $payload['publishNow'] = true;
    }
    if ($dryRun) return ['ok' => true, 'service' => 'zernio'];
    try {
        $resp = $zernio->createPost($payload);
        $postId = $resp['post']['_id'] ?? null;
        mirror_post_row($postId, 'zernio', $caption, $mediaType, $mediaUrl, $entry, $scheduledTs, $timezone, $resp['post']['status'] ?? 'scheduled', 'bulk');
        return ['ok' => true, 'service' => 'zernio', 'createdPostId' => $postId];
    } catch (Throwable $e) {
        return ['ok' => false, 'errors' => [$e->getMessage()]];
    }
}

function bulkpublish_row($bp, $bpLookup, $platform, $username, $caption, $mediaUrl, $mediaType, $scheduledFor, $scheduledTs, $timezone, $customContent, $dryRun) {
    $platform = bp_map_platform(strtolower($platform));
    $map = ['twitter' => 'x', 'googlebusiness' => 'gmb'];
    $slug = $map[$platform] ?? $platform;
    $lookupKey = strtolower($platform . '|' . $username);
    $channel = $bpLookup[$lookupKey] ?? null;
    if ($channel === null) {
        return ['ok' => false, 'errors' => ["no connected BulkPublish channel for platform:$platform username:$username"]];
    }
    $payload = [
        'content' => $caption,
        'channels' => [['channelId' => (int)$channel['id'], 'platform' => $slug]],
    ];
    if ($customContent !== '') $payload['platformContent'] = [$slug => $customContent];
    if ($mediaType !== 'caption') {
        try {
            $file = $bp->uploadMediaFromUrl($mediaUrl);
            if (!empty($file['id'])) $payload['mediaFiles'] = [(int)$file['id']];
        } catch (Throwable $e) {
            return ['ok' => false, 'errors' => ['media upload failed: ' . $e->getMessage()]];
        }
    }
    if ($scheduledFor) {
        $dt = new DateTime($scheduledFor, new DateTimeZone($timezone));
        $dt->setTimezone(new DateTimeZone('UTC'));
        $payload['status'] = 'scheduled';
        $payload['scheduledAt'] = $dt->format('Y-m-d\TH:i:s\Z');
        $payload['timezone'] = $timezone;
    } else {
        $payload['status'] = 'draft';
    }
    if ($dryRun) return ['ok' => true, 'service' => 'bulkpublish'];
    try {
        $resp = $bp->createPost($payload);
        $postId = $resp['id'] ?? null;
        if ($scheduledFor === null && $postId !== null) {
            $bp->publishPost($postId);
        }
        mirror_post_row($postId, 'bulkpublish', $caption, $mediaType, $mediaUrl, ['channelId' => (int)$channel['id'], 'platform' => $slug], $scheduledTs, $timezone, 'scheduled', 'bulk');
        return ['ok' => true, 'service' => 'bulkpublish', 'createdPostId' => $postId];
    } catch (Throwable $e) {
        return ['ok' => false, 'errors' => [$e->getMessage()]];
    }
}

function buffer_row($buf, $bufferLookup, $platform, $username, $caption, $mediaUrl, $mediaType, $scheduledFor, $scheduledTs, $timezone, $customContent, $dryRun) {
    if ($mediaType === 'video') {
        return ['ok' => false, 'errors' => ['buffer does not support video posts via its API (use caption or image)']];
    }
    $lookupKey = strtolower($platform . '|' . $username);
    $profile = $bufferLookup[$lookupKey] ?? null;
    if ($profile === null) {
        return ['ok' => false, 'errors' => ["no connected Buffer profile for platform:$platform username:$username"]];
    }
    if ($caption === '') {
        return ['ok' => false, 'errors' => ['buffer requires a caption']];
    }

    $opts = [];
    if ($mediaType !== 'caption' && $mediaUrl !== '') $opts['photoUrl'] = $mediaUrl;
    if ($scheduledFor) {
        $dt = new DateTime($scheduledFor, new DateTimeZone($timezone));
        $dt->setTimezone(new DateTimeZone('UTC'));
        $opts['scheduledAt'] = $dt->format('Y-m-d\TH:i:s\Z');
    } else {
        $opts['now'] = true;
    }

    if ($dryRun) return ['ok' => true, 'service' => 'buffer'];
    try {
        $resp = $buf->createUpdate([(string)$profile['id']], $caption, $opts);
        $u = $resp['updates'][0] ?? [];
        $postId = $u['id'] ?? null;
        $status = !empty($opts['now']) ? 'publishing' : buffer_map_status((string)($u['status'] ?? 'scheduled'));
        mirror_post_row($postId, 'buffer', $caption, $mediaType, $mediaUrl, ['profileId' => (string)$profile['id'], 'platform' => $platform], $scheduledTs, $timezone, $status, 'bulk');
        return ['ok' => true, 'service' => 'buffer', 'createdPostId' => $postId];
    } catch (Throwable $e) {
        return ['ok' => false, 'errors' => [$e->getMessage()]];
    }
}

function mirror_post_row($postId, $service, $caption, $mediaType, $mediaUrl, $entry, $scheduledTs, $timezone, $status, $source) {
    try {
        $stmt = db()->prepare(
            'INSERT INTO posts (zernio_post_id, service, content, media_type, media_json, platforms_json, scheduled_for, timezone, status, source)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $postId,
            $service,
            $caption !== '' ? $caption : null,
            $mediaType,
            $mediaType !== 'caption' ? json_encode([['url' => $mediaUrl, 'type' => $mediaType]]) : '[]',
            json_encode([$entry]),
            $scheduledTs ? date('Y-m-d H:i:s', $scheduledTs) : null,
            $timezone,
            $status,
            $source,
        ]);
    } catch (Throwable $e) {
        // non-fatal
    }
}

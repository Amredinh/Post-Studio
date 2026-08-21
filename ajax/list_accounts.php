<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/zernio.php';
require_once __DIR__ . '/../includes/bulkpublish.php';
require_once __DIR__ . '/../includes/buffer.php';
require_once __DIR__ . '/../includes/platforms.php';
require_login_ajax();

header('Content-Type: application/json');

$zernio = zernio_client();
$bp = bulkpublish_client();
$buf = buffer_client();

if (!$zernio && !$bp && !$buf) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No API key configured. Add a Zernio and/or BulkPublish and/or Buffer key in Settings.']);
    exit;
}

$services = ['zernio' => (bool)$zernio, 'bulkpublish' => (bool)$bp, 'buffer' => (bool)$buf];
$accounts = [];
$errors = [];

try {
    if ($zernio) {
        $data = $zernio->listAccounts('', 'connected');
        foreach (($data['accounts'] ?? []) as $a) {
            $meta = platform_meta($a['platform']);
            $accounts[] = array_merge($a, [
                'service' => 'zernio',
                'color' => $meta['color'],
                'short' => $meta['short'],
            ]);
        }
    }
} catch (Throwable $e) {
    $errors[] = 'Zernio: ' . $e->getMessage();
}

try {
    if ($bp) {
        $channels = $bp->listChannels()['channels'] ?? [];
        foreach ($channels as $c) {
            $platform = bp_map_platform((string)($c['platform'] ?? ''));
            $meta = platform_meta($platform);
            $accounts[] = [
                'service' => 'bulkpublish',
                '_id' => 'b' . $c['id'],
                'channelId' => $c['id'],
                'platform' => $platform,
                'displayName' => $c['accountName'] ?? '',
                'username' => $c['accountName'] ?? ($c['accountId'] ?? ''),
                'avatarUrl' => $c['profileImage'] ?? null,
                'accountId' => $c['accountId'] ?? null,
                'accountType' => $c['accountType'] ?? null,
                'tokenStatus' => $c['tokenStatus'] ?? null,
                'isActive' => $c['isActive'] ?? null,
                'color' => $meta['color'],
                'short' => $meta['short'],
            ];
        }
    }
} catch (Throwable $e) {
    $errors[] = 'BulkPublish: ' . $e->getMessage();
}

try {
    if ($buf) {
        $profiles = $buf->listProfiles()['profiles'] ?? [];
        foreach ($profiles as $p) {
            $platform = buffer_map_platform((string)($p['service'] ?? ''));
            $meta = platform_meta($platform);
            $accounts[] = [
                'service' => 'buffer',
                '_id' => 'bf' . ($p['id'] ?? ''),
                'profileId' => $p['id'] ?? '',
                'platform' => $platform,
                'displayName' => $p['formatted_username'] ?: ($p['display_name'] ?? ($p['service_username'] ?? '')),
                'username' => $p['formatted_username'] ?? ($p['service_username'] ?? ''),
                'avatarUrl' => $p['avatar_https'] ?? ($p['avatar'] ?? null),
                'accountId' => $p['service_id'] ?? null,
                'followers' => $p['statistics']['followers'] ?? null,
                'timezone' => $p['timezone'] ?? null,
                'color' => $meta['color'],
                'short' => $meta['short'],
            ];
        }
    }
} catch (Throwable $e) {
    $errors[] = 'Buffer: ' . $e->getMessage();
}

if (!$accounts && $errors) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => implode(' | ', $errors)]);
    exit;
}

echo json_encode(['ok' => true, 'accounts' => $accounts, 'services' => $services, 'errors' => $errors]);

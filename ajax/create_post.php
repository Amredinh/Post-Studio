<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/zernio.php';
require_once __DIR__ . '/../includes/bulkpublish.php';
require_once __DIR__ . '/../includes/db.php';
require_login_ajax();

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body']);
    exit;
}

$platforms = $payload['platforms'] ?? [];
if (!is_array($platforms) || count($platforms) === 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Select at least one channel.']);
    exit;
}

// Split channels by service.
$zernioEntries = [];
$bpEntries = [];
foreach ($platforms as $pl) {
    if (($pl['service'] ?? '') === 'bulkpublish') {
        $bpEntries[] = $pl;
    } else {
        $zernioEntries[] = $pl;
    }
}

$results = [];
$errors = [];

// ---- Zernio ----
if ($zernioEntries) {
    $zernio = zernio_client();
    if (!$zernio) {
        $errors[] = 'No Zernio API key configured.';
    } else {
        try {
            $zPayload = $payload;
            $zPayload['platforms'] = [];
            foreach ($zernioEntries as $e) {
                $zE = $e;
                unset($zE['service']);
                $zPayload['platforms'][] = $zE;
            }
            if (isset($zPayload['mediaItems']) && is_array($zPayload['mediaItems'])) {
                $zPayload['mediaItems'] = array_map(function ($m) {
                    if (isset($m['fileId'])) unset($m['fileId']);
                    return $m;
                }, $zPayload['mediaItems']);
            }
            $result = $zernio->createPost($zPayload);
            $post = $result['post'] ?? [];
            mirror_post($post, $zPayload, 'zernio', 'composer');
            $results[] = ['service' => 'zernio', 'post' => $post, 'message' => $result['message'] ?? 'Post created'];
        } catch (Throwable $e) {
            $errors[] = 'Zernio: ' . $e->getMessage();
        }
    }
}

// ---- BulkPublish ----
if ($bpEntries) {
    $bp = bulkpublish_client();
    if (!$bp) {
        $errors[] = 'No BulkPublish API key configured.';
    } else {
        try {
            $bpPayload = build_bp_payload($payload, $bpEntries, $bp);
            $post = $bp->createPost($bpPayload);
            $postId = $post['id'] ?? null;
            $postStatus = $post['status'] ?? null;

            // "Publish now" for BulkPublish: created as draft, then publish.
            if (!empty($payload['publishNow'])) {
                if ($postId !== null) {
                    $published = $bp->publishPost($postId);
                    $post = $published['post'] ?? $published ?? $post;
                    $postStatus = $post['status'] ?? $published['status'] ?? 'publishing';
                }
            }

            mirror_post($post, $payload, 'bulkpublish', 'composer', $bpEntries);
            $results[] = ['service' => 'bulkpublish', 'post' => $post, 'message' => 'BulkPublish post created'];
        } catch (Throwable $e) {
            $errors[] = 'BulkPublish: ' . $e->getMessage();
        }
    }
}

if (!$results) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => implode(' | ', $errors)]);
    exit;
}

echo json_encode(['ok' => true, 'posts' => $results, 'errors' => $errors]);

/**
 * Build a BulkPublish create-post payload from the unified composer payload.
 */
function build_bp_payload(array $payload, array $bpEntries, BulkPublish $bp): array {
    $channels = [];
    $platformSpecific = [];
    $platformContent = [];
    $postTypeOverrides = [];

    foreach ($bpEntries as $e) {
        $slug = bp_to_bp_slug((string)($e['platform'] ?? ''));
        $channels[] = ['channelId' => (int)($e['channelId'] ?? 0), 'platform' => $slug];
        if (!empty($e['platformSpecific']) && is_array($e['platformSpecific'])) {
            $perPlatform = $e['platformSpecific'];
            // _firstComment applies to the whole post in BulkPublish, not per platform.
            if (isset($perPlatform['_firstComment'])) {
                $firstComment = $perPlatform['_firstComment'];
                unset($perPlatform['_firstComment']);
            }
            if ($perPlatform) $platformSpecific[$slug] = $perPlatform;
        }
        if (!empty($e['customContent'])) {
            $platformContent[$slug] = $e['customContent'];
        }
        if (!empty($e['postTypeOverride'])) {
            $postTypeOverrides[$slug] = $e['postTypeOverride'];
        }
    }

    $bpPayload = [
        'content' => (string)($payload['content'] ?? ''),
        'channels' => $channels,
    ];
    if ($platformSpecific) $bpPayload['platformSpecific'] = $platformSpecific;
    if (!empty($firstComment)) $bpPayload['platformSpecific']['_firstComment'] = $firstComment;
    if ($platformContent) $bpPayload['platformContent'] = $platformContent;
    if ($postTypeOverrides) $bpPayload['postTypeOverrides'] = $postTypeOverrides;

    // Media: prefer an existing fileId, otherwise upload the URL to BulkPublish.
    $mediaIds = [];
    foreach (($payload['mediaItems'] ?? []) as $m) {
        if (!empty($m['fileId'])) {
            $mediaIds[] = (int)$m['fileId'];
        } elseif (!empty($m['url'])) {
            try {
                $file = $bp->uploadMediaFromUrl((string)$m['url']);
                if (!empty($file['id'])) $mediaIds[] = (int)$file['id'];
            } catch (Throwable $e) {
                // Skip media that cannot be fetched; keep going with the rest.
            }
        }
    }
    $mediaIds = array_values(array_filter($mediaIds));
    if ($mediaIds) $bpPayload['mediaFiles'] = $mediaIds;

    // Scheduling.
    if (!empty($payload['publishNow'])) {
        $bpPayload['status'] = 'draft';
    } elseif (!empty($payload['scheduledFor'])) {
        $tz = (string)($payload['timezone'] ?? 'UTC');
        $dt = new DateTime($payload['scheduledFor'], new DateTimeZone($tz));
        $dt->setTimezone(new DateTimeZone('UTC'));
        $bpPayload['status'] = 'scheduled';
        $bpPayload['scheduledAt'] = $dt->format('Y-m-d\TH:i:s\Z');
        $bpPayload['timezone'] = $tz;
    } else {
        $bpPayload['status'] = 'draft';
    }

    return $bpPayload;
}

/** Map internal platform names onto BulkPublish platform slugs. */
function bp_to_bp_slug(string $platform): string {
    $map = [
        'twitter' => 'x',
        'googlebusiness' => 'gmb',
        'snapchat' => 'snapchat',
        'whatsapp' => 'whatsapp',
        'slack' => 'slack',
    ];
    return $map[$platform] ?? $platform;
}

/** Mirror a created post into the local posts table (non-fatal). */
function mirror_post(array $post, array $payload, string $service, string $source, array $bpEntries = []): void {
    try {
        $platforms = $payload['platforms'] ?? [];
        $mediaType = 'caption';
        $mediaJson = '[]';
        if (!empty($payload['mediaItems']) && is_array($payload['mediaItems'])) {
            $hasVideo = false;
            foreach ($payload['mediaItems'] as $m) {
                if (($m['type'] ?? '') === 'video') { $hasVideo = true; break; }
            }
            $mediaType = $hasVideo ? 'video' : 'image';
            $mediaJson = json_encode($payload['mediaItems']);
        }
        $scheduledFor = null;
        if ($service === 'bulkpublish') {
            $scheduledFor = !empty($post['scheduledAt']) ? date('Y-m-d H:i:s', strtotime($post['scheduledAt'])) : null;
        } elseif (!empty($payload['scheduledFor'])) {
            $scheduledFor = date('Y-m-d H:i:s', strtotime($payload['scheduledFor']));
        }
        $postId = $service === 'bulkpublish' ? ($post['id'] ?? null) : ($post['_id'] ?? null);
        $status = $service === 'bulkpublish' ? ($post['status'] ?? 'scheduled') : ($post['status'] ?? 'scheduled');

        $stmt = db()->prepare(
            'INSERT INTO posts (zernio_post_id, service, content, media_type, media_json, platforms_json, scheduled_for, timezone, status, source)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $postId,
            $service,
            $payload['content'] ?? null,
            $mediaType,
            $mediaJson,
            json_encode($platforms),
            $scheduledFor,
            $payload['timezone'] ?? 'UTC',
            $status,
            $source,
        ]);
    } catch (Throwable $e) {
        // Non-fatal: DB logging failure must not break posting.
    }
}

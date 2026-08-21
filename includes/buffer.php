<?php
/**
 * Buffer API client (classic v1, plain cURL, no dependencies).
 * Token stored in settings table as `buffer_api_key`.
 * Docs: https://developers.buffer.com/api/
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

class BufferException extends Exception {
    public $status;
    public $payload;
    public function __construct(string $message, int $status = 0, $payload = null) {
        parent::__construct($message);
        $this->status = $status;
        $this->payload = $payload;
    }
}

class Buffer {
    private string $token;

    public function __construct(string $accessToken) {
        $this->token = $accessToken;
    }

    /** Core request helper. Returns decoded JSON array. */
    public function request(string $httpMethod, string $path, array $params = []): array {
        $url = BUFFER_BASE_URL . '/' . ltrim($path, '/');
        $ch = curl_init();

        if ($httpMethod === 'GET' && $params) {
            $url .= '?' . http_build_query($params);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Accept: application/json',
            ],
        ]);

        if ($httpMethod === 'POST') {
            // Classic Buffer API v1 speaks form-urlencoded, not JSON.
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);

        if ($curlErr !== '') {
            throw new BufferException('Connection error: ' . $curlErr);
        }

        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            throw new BufferException('Invalid response from Buffer (HTTP ' . $status . ')', $status, $raw);
        }

        if (isset($decoded['success']) && $decoded['success'] === false) {
            $msg = (string)($decoded['error'] ?? ('Buffer error code ' . ($decoded['code'] ?? $status)));
            throw new BufferException($msg, $status, $decoded);
        }

        if ($status >= 400) {
            $msg = is_string($raw) && $raw !== '' ? 'HTTP ' . $status : 'Buffer HTTP ' . $status;
            throw new BufferException('Buffer request failed (' . $msg . ')', $status, $decoded);
        }

        return $decoded;
    }

    /** GET /user.json — authenticated account info. */
    public function getUser(): array {
        return $this->request('GET', 'user.json');
    }

    /** GET /profiles.json — connected social profiles. */
    public function listProfiles(): array {
        $data = $this->request('GET', 'profiles.json');
        return ['profiles' => is_array($data) ? $data : []];
    }

    /** GET /profiles/{id}.json */
    public function getProfile(string $profileId): array {
        return $this->request('GET', 'profiles/' . rawurlencode($profileId) . '.json')['profile'] ?? [];
    }

    /**
     * POST /updates/create.json
     * $opts: now (bool), top (bool), scheduledAt (ISO8601 UTC), photoUrl, thumbnailUrl
     * Returns ['updates' => [...], 'buffer_count' => n].
     */
    public function createUpdate(array $profileIds, string $text, array $opts = []): array {
        $params = [];
        foreach (array_values(array_filter($profileIds)) as $i => $pid) {
            $params['profile_ids[' . $i . ']'] = $pid;
        }
        $params['text'] = $text;
        if (!empty($opts['now'])) $params['now'] = 'true';
        if (!empty($opts['top'])) $params['top'] = 'true';
        if (!empty($opts['scheduledAt'])) $params['scheduled_at'] = $opts['scheduledAt'];
        if (!empty($opts['photoUrl'])) $params['media[photo]'] = $opts['photoUrl'];
        if (!empty($opts['thumbnailUrl'])) $params['media[thumbnail]'] = $opts['thumbnailUrl'];
        return $this->request('POST', 'updates/create.json', $params);
    }

    /** GET /profiles/{id}/updates/sent.json */
    public function listSent(string $profileId, int $count = 50): array {
        $data = $this->request('GET', 'profiles/' . rawurlencode($profileId) . '/updates/sent.json', ['count' => min(100, max(1, $count))]);
        return ['updates' => $data['updates'] ?? []];
    }

    /** GET /profiles/{id}/updates/pending.json */
    public function listPending(string $profileId, int $count = 50): array {
        $data = $this->request('GET', 'profiles/' . rawurlencode($profileId) . '/updates/pending.json', ['count' => min(100, max(1, $count))]);
        return ['updates' => $data['updates'] ?? []];
    }

    /** GET /updates/{id}.json */
    public function getUpdate(string $updateId): array {
        return $this->request('GET', 'updates/' . rawurlencode($updateId) . '.json')['update'] ?? [];
    }

    /** POST /updates/{id}/share.json — share an existing update again (when=now). */
    public function sharePost(string $updateId): array {
        return $this->request('POST', 'updates/' . rawurlencode($updateId) . '/share.json', ['when' => 'now']);
    }

    /** POST /updates/{id}/destroy.json — remove the update from Buffer. */
    public function destroyPost(string $updateId): array {
        return $this->request('POST', 'updates/' . rawurlencode($updateId) . '/destroy.json');
    }
}

/** Build a Buffer client from the stored API key, or null if not set. */
function buffer_client(): ?Buffer {
    $key = (string)get_setting('buffer_api_key', '');
    if ($key === '') {
        return null;
    }
    return new Buffer($key);
}

/** Map Buffer service identifiers onto internal platform names. */
function buffer_map_platform(string $service): string {
    $map = [
        'google'         => 'googlebusiness',
        'gmb'            => 'googlebusiness',
        'googlebusiness' => 'googlebusiness',
    ];
    return $map[$service] ?? $service;
}

/** Normalize a raw Buffer update status into this app's status vocabulary. */
function buffer_map_status(string $bufferStatus): string {
    switch ($bufferStatus) {
        case 'sent':     return 'published';
        case 'buffered':
        case 'scheduled':return 'scheduled';
        case 'failed':
        case 'error':    return 'failed';
        default:         return $bufferStatus !== '' ? $bufferStatus : 'pending';
    }
}

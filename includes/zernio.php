<?php
/**
 * Zernio API client (plain cURL, no dependencies).
 * Base URL: https://zernio.com/api/v1
 */

require_once __DIR__ . '/../config.php';

class ZernioException extends Exception {
    public $status;
    public $payload;
    public function __construct(string $message, int $status = 0, $payload = null) {
        parent::__construct($message);
        $this->status = $status;
        $this->payload = $payload;
    }
}

class Zernio {
    private string $apiKey;
    private string $baseUrl;

    public function __construct(string $apiKey) {
        $this->apiKey = $apiKey;
        $this->baseUrl = ZERNIO_BASE_URL;
    }

    /** Core request helper. Returns decoded JSON array. */
    public function request(string $method, string $path, $body = null, array $query = [], array $headers = []): array {
        $url = $this->baseUrl . $path;
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $allHeaders = [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
        ];
        if ($body !== null || $method === 'POST') {
            $allHeaders[] = 'Content-Type: application/json';
        }
        foreach ($headers as $h) {
            $allHeaders[] = $h;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
        }
        if (strlen($this->apiKey) < 5) {
            throw new ZernioException('No valid Zernio API key configured. Add it in Settings.', 0);
        }

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') {
            throw new ZernioException('Connection error: ' . $curlErr);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = ['error' => 'Invalid response from Zernio (HTTP ' . $status . ')'];
        }

        if ($status >= 200 && $status < 300) {
            return $decoded;
        }

        $msg = $decoded['error'] ?? ('Zernio HTTP ' . $status);
        throw new ZernioException((string)$msg, $status, $decoded);
    }

    // ---- Accounts / Profiles ----

    /** GET /v1/accounts — list connected social accounts. */
    public function listAccounts(?string $profileId = null, string $status = 'connected'): array {
        $q = [];
        if ($profileId) $q['profileId'] = $profileId;
        if ($status) $q['status'] = $status;
        return $this->request('GET', '/accounts', null, $q);
    }

    /** GET /v1/profiles — list profiles. */
    public function listProfiles(): array {
        return $this->request('GET', '/profiles');
    }

    // ---- Posts ----

    /** POST /v1/posts — create / schedule / publish a post. */
    public function createPost(array $data): array {
        $headers = ['x-request-id: ' . self::uuid4()];
        return $this->request('POST', '/posts', $data, [], $headers);
    }

    /** GET /v1/posts — list posts with filters. */
    public function listPosts(array $filters = []): array {
        return $this->request('GET', '/posts', null, $filters);
    }

    /** GET /v1/posts/{postId} — fetch a single post. */
    public function getPost(string $postId): array {
        return $this->request('GET', '/posts/' . rawurlencode($postId));
    }

    /** POST /v1/posts/{postId}/retry — retry a failed post. */
    public function retryPost(string $postId): array {
        return $this->request('POST', '/posts/' . rawurlencode($postId) . '/retry');
    }

    /** POST /v1/posts/{postId}/unpublish — unpublish a published post. */
    public function unpublishPost(string $postId): array {
        return $this->request('POST', '/posts/' . rawurlencode($postId) . '/unpublish');
    }

    // ---- Media ----

    /** POST /v1/media/presign — get a presigned upload URL for a file. */
    public function presignMedia(string $filename, string $contentType): array {
        return $this->request('POST', '/media/presign', [
            'filename' => $filename,
            'contentType' => $contentType,
        ]);
    }

    // ---- Utilities ----

    public static function uuid4(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

/** Build a Zernio client from the stored API key, or null if not set. */
function zernio_client(): ?Zernio {
    $key = get_setting('zernio_api_key', '');
    if ($key === '') {
        return null;
    }
    return new Zernio($key);
}
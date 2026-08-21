<?php
/**
 * BulkPublish API client (plain cURL, no dependencies).
 * Base URL: https://app.bulkpublish.com
 * Auth: Bearer bp_... key (Settings > API Keys)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

class BulkPublishException extends Exception {
    public $status;
    public $payload;
    public function __construct(string $message, int $status = 0, $payload = null) {
        parent::__construct($message);
        $this->status = $status;
        $this->payload = $payload;
    }
}

class BulkPublish {
    private string $apiKey;
    private string $baseUrl;

    public function __construct(string $apiKey) {
        $this->apiKey = $apiKey;
        $this->baseUrl = BULKPUBLISH_BASE_URL;
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
        if ($body !== null && !($body instanceof CURLFile)) {
            $allHeaders[] = 'Content-Type: application/json';
        }
        foreach ($headers as $h) {
            $allHeaders[] = $h;
        }

        if (strlen($this->apiKey) < 5) {
            throw new BulkPublishException('No valid BulkPublish API key configured. Add it in Settings.', 0);
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
        if ($body instanceof CURLFile) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => $body]);
        } elseif ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
        }

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);

        if ($curlErr !== '') {
            throw new BulkPublishException('Connection error: ' . $curlErr);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = ['error' => ['message' => 'Invalid response from BulkPublish (HTTP ' . $status . ')']];
        }

        if ($status >= 200 && $status < 300) {
            return $decoded;
        }

        $err = $decoded['error'] ?? [];
        if (is_string($err)) {
            $msg = $err;
        } elseif (is_array($err)) {
            $msg = $err['message'] ?? 'BulkPublish HTTP ' . $status;
        } else {
            $msg = 'BulkPublish HTTP ' . $status;
        }
        throw new BulkPublishException((string)$msg, $status, $decoded);
    }

    // ---- Channels ----

    /** GET /api/channels — list connected channels. */
    public function listChannels(?bool $active = null): array {
        $q = [];
        if ($active !== null) {
            $q['active'] = $active ? 'true' : 'false';
        }
        return $this->request('GET', '/api/channels', null, $q);
    }

    /** GET /api/channels/{id}/options — platform-specific options (boards, subreddits, channels). */
    public function getChannelOptions($channelId): array {
        return $this->request('GET', '/api/channels/' . rawurlencode((string)$channelId) . '/options');
    }

    /** GET /api/platforms — availability + post types per platform. */
    public function listPlatforms(): array {
        return $this->request('GET', '/api/platforms');
    }

    // ---- Posts ----

    /** POST /api/posts — create / schedule a post. */
    public function createPost(array $data): array {
        return $this->request('POST', '/api/posts', $data);
    }

    /** GET /api/posts — list posts with filters. */
    public function listPosts(array $filters = []): array {
        return $this->request('GET', '/api/posts', null, $filters);
    }

    /** GET /api/posts/{postId} — fetch a single post. */
    public function getPost($postId): array {
        return $this->request('GET', '/api/posts/' . rawurlencode((string)$postId));
    }

    /** POST /api/posts/{postId}/publish — publish a draft now. */
    public function publishPost($postId): array {
        return $this->request('POST', '/api/posts/' . rawurlencode((string)$postId) . '/publish');
    }

    /** POST /api/posts/{postId}/retry — retry a failed post. */
    public function retryPost($postId): array {
        return $this->request('POST', '/api/posts/' . rawurlencode((string)$postId) . '/retry');
    }

    /** POST /api/posts/{postId}/story — also share as a story (when supported). */
    public function storyPost($postId): array {
        return $this->request('POST', '/api/posts/' . rawurlencode((string)$postId) . '/story');
    }

    // ---- Media ----

    /** POST /api/media — upload a file (multipart). Returns the file record. */
    public function uploadMedia(string $filePath, string $filename, string $mime): array {
        if (!file_exists($filePath)) {
            throw new BulkPublishException('Upload source file not found: ' . $filename);
        }
        $curlFile = new CURLFile($filePath, $mime, $filename);
        $result = $this->request('POST', '/api/media', $curlFile);
        return $result['file'] ?? $result;
    }

    /** Upload a file straight from a public URL. Returns the file record. */
    public function uploadMediaFromUrl(string $url): array {
        if (!preg_match('#^https?://#i', $url)) {
            throw new BulkPublishException('Media URL must be a public http(s) URL');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'bpmedia');
        if ($tmp === false) {
            throw new BulkPublishException('Could not create a temporary file for media download');
        }
        $ch = curl_init($url);
        $fp = fopen($tmp, 'wb');
        if ($fp === false) {
            @unlink($tmp);
            throw new BulkPublishException('Could not open temporary file for media download');
        }
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 20,
        ]);
        curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        fclose($fp);

        if ($status < 200 || $status >= 300 || filesize($tmp) === 0) {
            @unlink($tmp);
            throw new BulkPublishException('Could not download media from URL (HTTP ' . $status . ')');
        }

        $name = basename(parse_url($url, PHP_URL_PATH)) ?: 'media';
        if ($contentType && is_string($contentType)) {
            $mime = trim(explode(';', $contentType)[0]);
        } else {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $map = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'mp4' => 'video/mp4', 'mov' => 'video/quicktime', 'webm' => 'video/webm'];
            $mime = $map[$ext] ?? 'application/octet-stream';
        }

        try {
            $file = $this->uploadMedia($tmp, $name, $mime);
        } finally {
            @unlink($tmp);
        }
        return $file;
    }

    /** DELETE /api/media/{id} — remove a media file. */
    public function deleteMedia($mediaId): array {
        return $this->request('DELETE', '/api/media/' . rawurlencode((string)$mediaId));
    }
}

/** Build a BulkPublish client from the stored API key, or null if not set. */
function bulkpublish_client(): ?BulkPublish {
    $key = get_setting('bulkpublish_api_key', '');
    if ($key === '') {
        return null;
    }
    return new BulkPublish($key);
}

/** Map BulkPublish platform slugs onto the internal platform names used elsewhere. */
function bp_map_platform(string $platform): string {
    $map = [
        'x' => 'twitter',
        'gmb' => 'googlebusiness',
    ];
    return $map[$platform] ?? $platform;
}

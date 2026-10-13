<?php
/**
 * Buffer API client — GraphQL (api.buffer.com, Bearer token).
 *
 * Buffer retired the classic v1 REST API (api.bufferapp.com/1/) in 2024.
 * This client uses the current GraphQL endpoint with standard Bearer auth.
 *
 * Token: generate at publish.buffer.com/settings/api
 * Docs:  https://buffer.com/developers/api
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

/* Fallback for configs created before the GraphQL migration. */
defined('BUFFER_BASE_URL') || define('BUFFER_BASE_URL', 'https://api.buffer.com');

class BufferException extends Exception {
    public $status;
    public $payload;
    public function __construct(string $message, int $status = 0, $payload = null) {
        parent::__construct($message);
        $this->status  = $status;
        $this->payload = $payload;
    }
}

class Buffer {
    private string $token;
    private ?string $cachedOrgId = null;

    public function __construct(string $accessToken) {
        $this->token = $accessToken;
    }

    // ---------------------------------------------------------------
    // Core GraphQL helper
    // ---------------------------------------------------------------

    /**
     * Execute a GraphQL query or mutation against api.buffer.com.
     * Returns the `data` portion of the response.
     * Throws BufferException on HTTP errors or top-level GraphQL errors.
     */
    public function graphql(string $query, array $variables = []): array {
        $ch  = curl_init();
        $url = rtrim(BUFFER_BASE_URL, '/');

        $body = json_encode(['query' => $query, 'variables' => $variables]);

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $raw     = curl_exec($ch);
        $status  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') {
            throw new BufferException('Connection error: ' . $curlErr);
        }

        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            throw new BufferException('Invalid response from Buffer (HTTP ' . $status . ')', $status, $raw);
        }

        if ($status >= 400) {
            $msg = $decoded['errors'][0]['message'] ?? ('Buffer HTTP ' . $status);
            throw new BufferException($msg, $status, $decoded);
        }

        // Top-level GraphQL errors (status 200 but errors key present)
        if (!empty($decoded['errors'])) {
            $msg = $decoded['errors'][0]['message'] ?? 'Buffer GraphQL error';
            throw new BufferException($msg, $status, $decoded);
        }

        return $decoded['data'] ?? [];
    }

    // ---------------------------------------------------------------
    // Account / Organisation
    // ---------------------------------------------------------------

    /**
     * GET account info including organization IDs.
     * Returns [ 'id', 'name', 'email', 'organizations' => [ ['id', 'name'] ] ]
     */
    public function getAccount(): array {
        $data = $this->graphql('
            query GetAccount {
                account {
                    id
                    name
                    email
                    organizations {
                        id
                        name
                    }
                }
            }
        ');
        return $data['account'] ?? [];
    }

    /**
     * Returns the first organization ID for the authenticated user.
     * Result is cached for the lifetime of this object.
     */
    public function getOrgId(): string {
        if ($this->cachedOrgId !== null) {
            return $this->cachedOrgId;
        }
        $account = $this->getAccount();
        $orgs    = $account['organizations'] ?? [];
        if (empty($orgs)) {
            throw new BufferException('No organizations found for this Buffer account.');
        }
        $this->cachedOrgId = (string)$orgs[0]['id'];
        return $this->cachedOrgId;
    }

    // ---------------------------------------------------------------
    // Channels (replaces listProfiles)
    // ---------------------------------------------------------------

    /**
     * List all connected channels for the account's first organization.
     * Returns [ 'channels' => [ ['id', 'name', 'service', 'avatar', 'timezone'] ] ]
     */
    public function listChannels(): array {
        $orgId = $this->getOrgId();
        $data  = $this->graphql('
            query ListChannels($orgId: String!) {
                channels(input: { organizationId: $orgId }) {
                    id
                    name
                    service
                    avatar
                    timezone
                    isLocked
                    status
                }
            }
        ', ['orgId' => $orgId]);
        return ['channels' => $data['channels'] ?? []];
    }

    // ---------------------------------------------------------------
    // Posts
    // ---------------------------------------------------------------

    /**
     * Create a post for a single channel.
     *
     * $opts keys:
     *   publishNow  (bool)   — share immediately
     *   scheduledAt (string) — ISO-8601 UTC datetime for scheduled posts
     *   mediaUrls   (array)  — public image / video URLs
     *
     * Returns the created post array.
     */
    public function createPost(string $channelId, string $text, array $opts = []): array {
        if (!empty($opts['publishNow'])) {
            $mode = 'now';
        } elseif (!empty($opts['scheduledAt'])) {
            $mode = 'customScheduled';
        } else {
            $mode = 'addToQueue';
        }

        $input = [
            'channelId'     => $channelId,
            'text'          => $text,
            'schedulingType'=> 'automatic',
            'mode'          => $mode,
        ];

        if ($mode === 'customScheduled' && !empty($opts['scheduledAt'])) {
            $input['dueAt'] = $opts['scheduledAt'];
        }

        if (!empty($opts['mediaUrls']) && is_array($opts['mediaUrls'])) {
            $input['media'] = array_map(function ($url) {
                return ['url' => $url];
            }, $opts['mediaUrls']);
        }

        $data = $this->graphql('
            mutation CreatePost($input: CreatePostInput!) {
                createPost(input: $input) {
                    ... on PostActionSuccess {
                        post {
                            id
                            text
                            status
                            dueAt
                            channelId
                        }
                    }
                    ... on MutationError {
                        message
                    }
                }
            }
        ', ['input' => $input]);

        $result = $data['createPost'] ?? [];

        if (isset($result['message']) && !isset($result['post'])) {
            throw new BufferException('Buffer createPost failed: ' . $result['message']);
        }

        return $result['post'] ?? [];
    }

    /**
     * List posts for a channel, filtered by status list.
     * $statuses: e.g. ['sent'], ['pending', 'draft', 'scheduled']
     * Returns [ 'posts' => [...] ]
     */
    public function listPosts(string $channelId, array $statuses = [], int $limit = 50): array {
        $orgId = $this->getOrgId();
        $first = min(100, max(1, $limit));

        $filter = ['channelIds' => [$channelId]];
        if ($statuses) {
            $filter['status'] = $statuses;
        }

        $data = $this->graphql('
            query ListPosts($orgId: String!, $filter: PostsFilter, $first: Int) {
                posts(
                    first: $first,
                    input: {
                        organizationId: $orgId,
                        filter: $filter,
                        sort: [{ field: dueAt, direction: desc }]
                    }
                ) {
                    edges {
                        node {
                            id
                            text
                            status
                            dueAt
                            channelId
                        }
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                }
            }
        ', ['orgId' => $orgId, 'filter' => $filter, 'first' => $first]);

        $edges = $data['posts']['edges'] ?? [];
        $posts = array_map(function ($e) { return $e['node']; }, $edges);
        return ['posts' => $posts];
    }

    /**
     * Get a single post by ID.
     */
    public function getPost(string $postId): array {
        $data = $this->graphql('
            query GetPost($id: String!) {
                post(id: $id) {
                    id
                    text
                    status
                    dueAt
                    channelId
                }
            }
        ', ['id' => $postId]);
        return $data['post'] ?? [];
    }

    /**
     * Delete (remove) a post by ID.
     */
    public function deletePost(string $postId): array {
        $data = $this->graphql('
            mutation DeletePost($input: DeletePostInput!) {
                deletePost(input: $input) {
                    ... on DeletePostSuccess {
                        id
                    }
                    ... on MutationError {
                        message
                    }
                }
            }
        ', ['input' => ['postId' => $postId]]);

        $result = $data['deletePost'] ?? [];
        if (isset($result['message']) && !isset($result['id'])) {
            throw new BufferException('Buffer deletePost failed: ' . $result['message']);
        }
        return $result;
    }

    /**
     * Share / re-queue a post immediately.
     * The new API has no direct "share again" equivalent; we attempt
     * a createPost for the same channel with the same text ("publish now").
     * This method is a best-effort wrapper kept for action.php compatibility.
     */
    public function sharePost(string $postId): array {
        // Fetch the original post so we can recreate it.
        $post = $this->getPost($postId);
        if (empty($post['channelId']) || empty($post['text'])) {
            throw new BufferException('Cannot re-share: original post data unavailable.');
        }
        return $this->createPost((string)$post['channelId'], (string)$post['text'], ['publishNow' => true]);
    }

    // ---------------------------------------------------------------
    // Backwards-compat aliases used by list_accounts.php
    // ---------------------------------------------------------------

    /**
     * @deprecated Use listChannels() — kept so list_accounts.php mapping works.
     */
    public function listProfiles(): array {
        $result   = $this->listChannels();
        $channels = $result['channels'] ?? [];
        // Remap to old-style profile shape so list_accounts.php requires minimal changes.
        $profiles = array_map(function ($c) {
            return [
                'id'                => $c['id'],
                'service'           => $c['service'],
                'display_name'      => $c['name'] ?? '',
                'formatted_username'=> $c['name'] ?? '',
                'service_username'  => $c['name'] ?? '',
                'avatar_https'      => $c['avatar'] ?? null,
                'timezone'          => $c['timezone'] ?? null,
                '_gql_channel'      => true, // flag for list_accounts.php to skip bf prefix mapping
            ];
        }, $channels);
        return ['profiles' => $profiles];
    }

    /** @deprecated Use getAccount(). Kept for settings.php test. */
    public function getUser(): array {
        return $this->getAccount();
    }
}

// ---------------------------------------------------------------
// Factory
// ---------------------------------------------------------------

/** Build a Buffer client from the stored API key, or null if not set. */
function buffer_client(): ?Buffer {
    $key = (string)get_setting('buffer_api_key', '');
    if ($key === '') {
        return null;
    }
    return new Buffer($key);
}

// ---------------------------------------------------------------
// Platform / status mappers
// ---------------------------------------------------------------

/** Map Buffer service identifiers onto internal platform names. */
function buffer_map_platform(string $service): string {
    $map = [
        'google'         => 'googlebusiness',
        'gmb'            => 'googlebusiness',
        'googlebusiness' => 'googlebusiness',
    ];
    return $map[$service] ?? $service;
}

/** Normalize a raw Buffer post status into this app's status vocabulary. */
function buffer_map_status(string $bufferStatus): string {
    switch ($bufferStatus) {
        case 'sent':
        case 'published':  return 'published';
        case 'scheduled':
        case 'pending':
        case 'draft':      return 'scheduled';
        case 'failed':
        case 'error':      return 'failed';
        default:           return $bufferStatus !== '' ? $bufferStatus : 'pending';
    }
}

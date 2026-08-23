<?php
/**
 * Telegram Bot API client (plain cURL, no dependencies).
 * Token stored in settings table as `telegram_bot_token`.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

class TelegramException extends Exception {
    public $status;
    public $payload;
    public function __construct(string $message, int $status = 0, $payload = null) {
        parent::__construct($message);
        $this->status = $status;
        $this->payload = $payload;
    }
}

class Telegram {
    private string $botToken;
    private string $baseUrl;

    public function __construct(string $botToken) {
        $this->botToken = $botToken;
        $this->baseUrl = 'https://api.telegram.org/bot' . $botToken;
    }

    /** Core request helper. Returns decoded JSON array ("result" value). */
    public function request(string $method, $body = null, array $query = []): array {
        $url = $this->baseUrl . '/' . $method;
        $fullUrl = $url . ($query ? '?' . http_build_query($query) : '');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);

        if ($curlErr !== '') {
            throw new TelegramException('Connection error: ' . $curlErr);
        }

        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            throw new TelegramException('Invalid response from Telegram (HTTP ' . $status . ')');
        }

        if (!isset($decoded['ok']) || !$decoded['ok']) {
            $err = $decoded['description'] ?? 'Telegram HTTP ' . $status;
            throw new TelegramException((string)$err, $status, $decoded);
        }

        return $decoded['result'] ?? $decoded;
    }

    /** GET https://api.telegram.org/bot<token>/getMe */
    public function getMe(): array {
        return $this->request('getMe');
    }

    /** GET https://api.telegram.org/bot<token>/getUpdates */
    public function getUpdates(int $offset = 0, int $limit = 100, int $timeout = 0): array {
        return $this->request('getUpdates', null, ['offset' => $offset, 'limit' => $limit, 'timeout' => $timeout]);
    }

    /** POST https://api.telegram.org/bot<token>/sendMessage */
    public function sendMessage(string $chatId, string $text, string $parseMode = 'HTML', array $replyMarkup = [], bool $disablePreview = true): array {
        $body = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => $disablePreview,
        ];
        if ($replyMarkup) {
            $body['reply_markup'] = $replyMarkup;
        }
        return $this->request('sendMessage', $body);
    }

    /** POST https://api.telegram.org/bot<token>/sendPhoto */
    public function sendPhoto(string $chatId, string $photo, string $caption = '', string $parseMode = 'HTML', array $replyMarkup = []): array {
        $body = [
            'chat_id' => $chatId,
            'photo' => $photo,
            'caption' => $caption,
            'parse_mode' => $parseMode,
        ];
        if ($replyMarkup) {
            $body['reply_markup'] = $replyMarkup;
        }
        return $this->request('sendPhoto', $body);
    }

    /** POST https://api.telegram.org/bot<token>/sendDocument */
    public function sendDocument(string $chatId, string $document, string $caption = '', string $parseMode = 'HTML', array $replyMarkup = []): array {
        $body = [
            'chat_id' => $chatId,
            'document' => $document,
            'caption' => $caption,
            'parse_mode' => $parseMode,
        ];
        if ($replyMarkup) {
            $body['reply_markup'] = $replyMarkup;
        }
        return $this->request('sendDocument', $body);
    }

    /** Show a chat action like "typing…" for ~5s. */
    public function sendChatAction(string $chatId, string $action = 'typing'): bool {
        try {
            $this->request('sendChatAction', ['chat_id' => $chatId, 'action' => $action]);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Acknowledge a callback query (stops the client loading spinner). */
    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): bool {
        $body = ['callback_query_id' => $callbackQueryId];
        if ($text !== '') {
            $body['text'] = $text;
            $body['show_alert'] = $showAlert;
        }
        try {
            $this->request('answerCallbackQuery', $body);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Edit the text + keyboard of an existing bot message. */
    public function editMessageText(string $chatId, int $messageId, string $text, string $parseMode = 'HTML', array $replyMarkup = [], bool $disablePreview = true): array {
        $body = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => $disablePreview,
        ];
        if ($replyMarkup) {
            $body['reply_markup'] = $replyMarkup;
        }
        return $this->request('editMessageText', $body);
    }

    /** Get webhook info */
    public function getWebhookInfo(): array {
        return $this->request('getWebhookInfo');
    }

    /** Set webhook */
    public function setWebhook(string $url): array {
        return $this->request('setWebhook', ['url' => $url]);
    }

    /** Delete webhook */
    public function deleteWebhook(): array {
        return $this->request('deleteWebhook');
    }
}

/** Build a Telegram client from the stored bot token, or null if not set. */
function telegram_client(): ?Telegram {
    $key = get_setting('telegram_bot_token', '');
    if ($key === '') {
        return null;
    }
    return new Telegram($key);
}

/* ---- Access-control helpers shared by telegram.php and the webhook ---- */

/** Decode the allowed-chats setting into an array of chat entries. */
function telegram_allowed_chats(): array {
    $raw = get_setting('telegram_allowed_chats', '[]');
    $list = json_decode((string)$raw, true);
    return is_array($list) ? $list : [];
}

/** True when this chat id is authorized to use the bot. */
function telegram_chat_allowed($chatId): bool {
    foreach (telegram_allowed_chats() as $c) {
        if ((string)($c['id'] ?? '') === (string)$chatId) {
            return true;
        }
    }
    return false;
}

/** Add a chat to the allowed list. */
function telegram_allow_chat($chatId, string $title, string $username): void {
    $list = telegram_allowed_chats();
    foreach ($list as $i => $c) {
        if ((string)($c['id'] ?? '') === (string)$chatId) {
            unset($list[$i]);
        }
    }
    $list = array_values($list);
    $list[] = [
        'id' => (string)$chatId,
        'title' => $title,
        'username' => $username,
        'added_at' => date('Y-m-d H:i:s'),
    ];
    set_setting('telegram_allowed_chats', json_encode($list));
}

/** Remove a chat from the allowed list. */
function telegram_revoke_chat($chatId): void {
    $list = telegram_allowed_chats();
    $out = [];
    foreach ($list as $c) {
        if ((string)($c['id'] ?? '') !== (string)$chatId) {
            $out[] = $c;
        }
    }
    set_setting('telegram_allowed_chats', json_encode(array_values($out)));
}

/** Generate a fresh pairing code valid for 15 minutes. */
function telegram_new_pair_code(): string {
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    set_setting('telegram_pair_code', $code);
    set_setting('telegram_pair_expires', date('Y-m-d H:i:s', time() + 900));
    return $code;
}

/** Check a submitted pairing code against the stored one (still valid). */
function telegram_pair_code_valid(string $code): bool {
    $stored = (string)get_setting('telegram_pair_code', '');
    $expires = (string)get_setting('telegram_pair_expires', '');
    if ($stored === '' || $expires === '') {
        return false;
    }
    if (strtotime($expires) < time()) {
        return false;
    }
    return hash_equals($stored, trim($code));
}

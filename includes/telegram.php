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

    /** Core request helper. Returns decoded JSON array. */
    private function request(string $method, string $path, $body = null, array $query = []): array {
        $url = $this->baseUrl . '/' . $method; // method here is the API method name like 'sendMessage'
        // Note: Telegram API uses GET for some methods, POST for others.
        // We'll just POST body for methods that need it, GET for others.
        // The caller should specify the correct HTTP method.

        $ch = curl_init();
        $fullUrl = $url . ($query ? '?' . http_build_query($query) : '');

        curl_setopt_array($ch, [
            CURLOPT_URL => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POST true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);

        if ($curlErr !== '') {
            curl_close($ch);
            throw new TelegramException('Connection error: ' . $curlErr);
        }

        curl_close($ch);

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new TelegramException('Invalid response from Telegram (HTTP ' . $status . ')');
        }

        if (!isset($decoded['ok']) || !$decoded['ok']) {
            $err = $decoded['description'] ?? 'Telegram HTTP ' . $status;
            throw new TelegramException((string)$err, $status, $decoded);
        }

        return $decoded['result'] ?? $decoded;
    }

    /** GET https://api.telegram.org/bot<token>/getUpdates */
    public function getUpdates(int $offset = 0, int $limit = 100, string $timeout = 0): array {
        return $this->request('getUpdates', [], null, ['offset' => $offset, 'limit' => $limit, 'timeout' => $timeout]);
    }

    /** POST https://api.telegram.org/bot<token>/sendMessage */
    public function sendMessage(string $chatId, string $text, string $parseMode = 'Markdown', array $replyMarkup = []): array {
        return $this->request('sendMessage', [], [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            ...$replyMarkup,
        ]);
    }

    /** POST https://api.telegram.org/bot<token>/sendPhoto */
    public function sendPhoto(string $chatId, string $photo, string $caption = '', string $parseMode = 'Markdown'): array {
        return $this->request('sendPhoto', [], [
            'chat_id' => $chatId,
            'photo' => $photo,
            'caption' => $caption,
            'parse_mode' => $parseMode,
        ]);
    }

    /** POST https://api.telegram.org/bot<token>/sendDocument */
    public function sendDocument(string $chatId, string $document, string $caption = '', string $parseMode = 'Markdown'): array {
        return $this->request('sendDocument', [], [
            'chat_id' => $chatId,
            'document' => $document,
            'caption' => $caption,
            'parse_mode' => $parseMode,
        ]);
    }

    /** Get webhook info */
    public function getWebhookInfo(): array {
        return $this->request('getWebhookInfo', []);
    }

    /** Set webhook */
    public function setWebhook(string $url): array {
        return $this->request('setWebhook', [], ['url' => $url]);
    }

    /** Delete webhook */
    public function deleteWebhook(): array {
        return $this->request('deleteWebhook', []);
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
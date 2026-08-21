<?php
require_once __DIR__ . '/../includes/auth.php';
require_login_ajax();

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$update = json_decode($raw, true);
if (!is_array($update)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid update']);
    exit;
}

// Get the bot token
$botToken = (string)get_setting('telegram_bot_token', '');
if ($botToken === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No Telegram bot token configured']);
    exit;
}

$telegram = new Telegram($botToken);

// Determine the message and chat from the update
$message = $update['message'] ?? [];
$chatId = ($message['chat'] ?? [])['id'] ?? 0;
$text = $message['text'] ?? '';

if ($chatId === 0 || $text === '') {
    echo json_encode(['ok' => true]);
    exit;
}

// Handle bot commands
$response = null;

 // /post command - create a post from Telegram
if ($text === '/post' || str_starts_with($text, '/post ')) {
    $command = str_starts_with($text, '/post ') ? substr($text, 6) : '';
    // Parse potential arguments: /post caption|url|platform
    // For simplicity, we'll just echo back what we can do
    // In a full implementation, this would parse media, caption, platforms from the command
    $response = ['ok' => true, 'message' => 'Use /post caption to create a text post, or /post help for options'];
}

// /status command - check post status
elseif ($text === '/status') {
    // List recent posts - we'll show the last 5 posts
    try {
        $posts = [];
        // Check Zernio posts
        $zernio = zernio_client();
        if ($zernio) {
            $zData = $zernio->listPosts(['limit' => 5]);
            foreach (($zData['posts'] ?? []) as $p) {
                $posts[] = ['service' => 'zernio', 'id' => $p['_id'] ?? '', 'status' => $p['status'] ?? '', 'content' => $p['content'] ?? ''];
            }
        }
        // Check BulkPublish posts
        $bp = bulkpublish_client();
        if ($bp) {
            $bData = $bp->listPosts(['limit' => 5]);
            foreach (($bData['posts'] ?? []) as $p) {
                $postId = 'b' . ($p['id'] ?? '');
                $posts[] = ['service' => 'bulkpublish', 'id' => $postId, 'status' => $p['status'] ?? '', 'content' => $p['content'] ?? ''];
            }
        }
        // Sort by creation time, newest first
        usort($posts, function ($a, $b) {
            $ta = $a['id'] ?? '';
            $tb = $b['id'] ?? '';
            return strcmp($tb, $ta);
        });
        $response = ['ok' => true, 'message' => 'Recent posts:', 'posts' => array_slice($posts, 0, 5)];
    } catch (Throwable $e) {
        $response = ['ok' => false, 'error' => $e->getMessage()];
    }
}

// /help command - show available commands
elseif ($text === '/help') {
    $response = ['ok' => true, 'message' => 'Available commands:/n/post caption - Create text post/status:n/status - Check post status/n/help - Show this help'];
}

// Default - echo the message back
else {
    $response = ['ok' => true, 'message' => 'Unknown command. Use /help for available commands.'];
}

// Send response back to the user via Telegram
if ($response && isset($response['message'])) {
    try {
        $tgResponse = $telegram->sendMessage($chatId, $response['message']);
        // Note: In a full implementation, you'd want to handle errors here
    } catch (Throwable $e) {
        // Failed to send message, but we already responded to the update
    }
}

echo json_encode(['ok' => true]);
<?php
/**
 * PDO database connection helper.
 */
require_once __DIR__ . '/../config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

/** Fetch a single setting value from the settings table. */
function get_setting(string $key, ?string $default = null): ?string {
    try {
        $stmt = db()->prepare('SELECT `value` FROM settings WHERE `key` = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

/** Save a setting value. */
function set_setting(string $key, ?string $value): void {
    $stmt = db()->prepare(
        'INSERT INTO settings (`key`, `value`) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
    );
    $stmt->execute([$key, $value]);
}
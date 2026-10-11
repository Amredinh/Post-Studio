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

/** Increment engagement view count for a post. */
function increment_engagement(string $service, string $postId): void {
    try {
        $stmt = db()->prepare(
            'INSERT INTO posts_engagement (service, post_id, viewed_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE viewed_at = VALUES(viewed_at)'
        );
        $stmt->execute([$service, $postId]);
    } catch (Throwable $e) {
        // Non-fatal.
    }
}

/** Get total engagement views (number of post detail pages opened in the app). */
function get_engagement_views(): int {
    try {
        $stmt = db()->query('SELECT COUNT(*) AS cnt FROM posts_engagement');
        $row = $stmt->fetch();
        return (int)($row['cnt'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Create the analytics history table on demand, so no manual SQL import is needed
 * when upgrading an existing install.
 */
function ensure_analytics_schema(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS analytics_snapshots (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                snap_date DATE NOT NULL,
                service VARCHAR(20) NOT NULL,
                post_id VARCHAR(64) NOT NULL,
                views BIGINT NOT NULL DEFAULT 0,
                likes BIGINT NOT NULL DEFAULT 0,
                comments BIGINT NOT NULL DEFAULT 0,
                shares BIGINT NOT NULL DEFAULT 0,
                UNIQUE KEY uk_snap (snap_date, service, post_id),
                INDEX idx_snap_date (snap_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } catch (Throwable $e) {
        // Non-fatal: charts fall back to publish-date data.
    }
}

/** Store today's counters for each post (one row per post per day). */
function save_analytics_snapshots(array $rows): void {
    if (!$rows) return;
    ensure_analytics_schema();
    try {
        $pdo = db();
        $stmt = $pdo->prepare(
            'INSERT INTO analytics_snapshots (snap_date, service, post_id, views, likes, comments, shares)
             VALUES (CURDATE(), ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE views = VALUES(views), likes = VALUES(likes),
                                     comments = VALUES(comments), shares = VALUES(shares)'
        );
        $pdo->beginTransaction();
        foreach ($rows as $r) {
            $stmt->execute([
                (string)$r['service'], substr((string)$r['post_id'], 0, 64),
                (int)$r['views'], (int)$r['likes'], (int)$r['comments'], (int)$r['shares'],
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        try { if (db()->inTransaction()) db()->rollBack(); } catch (Throwable $e2) {}
    }
}

/** Daily account-wide totals recorded by save_analytics_snapshots(). */
function get_analytics_history(int $days = 90): array {
    ensure_analytics_schema();
    try {
        $since = date('Y-m-d', strtotime('-' . max(1, $days) . ' days'));
        $stmt = db()->prepare(
            'SELECT snap_date AS d, SUM(views) AS views, SUM(likes) AS likes,
                    SUM(comments) AS comments, SUM(shares) AS shares
             FROM analytics_snapshots WHERE snap_date >= ?
             GROUP BY snap_date ORDER BY snap_date ASC'
        );
        $stmt->execute([$since]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[] = [
                'date' => $r['d'], 'views' => (int)$r['views'], 'likes' => (int)$r['likes'],
                'comments' => (int)$r['comments'], 'shares' => (int)$r['shares'],
            ];
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

<?php
/**
 * Post Studio - Zernio bulk posting tool
 * Configuration file (edit database credentials here)
 */

// ---- MySQL credentials (cPanel) ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// ---- Zernio API ----
define('ZERNIO_BASE_URL', 'https://zernio.com/api/v1');

// ---- BulkPublish API ----
define('BULKPUBLISH_BASE_URL', 'https://app.bulkpublish.com');

// ---- App settings ----
define('APP_NAME', 'Post Studio');
define('APP_SECRET', 'CHANGE_THIS_TO_A_LONG_RANDOM_STRING');
define('SESSION_NAME', 'poststudio_session');

// Show pretty errors while developing; set to false in production
define('APP_DEBUG', true);

date_default_timezone_set('UTC');

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
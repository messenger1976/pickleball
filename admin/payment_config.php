<?php
/**
 * PayMongo config — set live/test keys for online payments.
 * Leave secret empty to keep checkout in "demo mode" (marks paid locally).
 *
 * Preferred: set env vars, or create admin/payment_config.local.php (gitignored).
 */
$localConfig = __DIR__ . '/payment_config.local.php';
if (is_file($localConfig)) {
    include_once $localConfig;
}

if (!defined('PB_PAYMONGO_SECRET_KEY')) {
    define('PB_PAYMONGO_SECRET_KEY', getenv('PB_PAYMONGO_SECRET_KEY') ?: '');
}
if (!defined('PB_PAYMONGO_PUBLIC_KEY')) {
    define('PB_PAYMONGO_PUBLIC_KEY', getenv('PB_PAYMONGO_PUBLIC_KEY') ?: '');
}
if (!defined('PB_PAYMONGO_WEBHOOK_SECRET')) {
    define('PB_PAYMONGO_WEBHOOK_SECRET', getenv('PB_PAYMONGO_WEBHOOK_SECRET') ?: '');
}
if (!defined('PB_REALTIME_URL')) {
    define('PB_REALTIME_URL', getenv('PB_REALTIME_URL') ?: 'http://127.0.0.1:3001');
}
if (!defined('PB_REALTIME_SECRET')) {
    define('PB_REALTIME_SECRET', getenv('PB_REALTIME_SECRET') ?: 'pickleball-realtime-dev');
}

function pb_paymongo_configured() {
    return PB_PAYMONGO_SECRET_KEY !== '';
}

function pb_app_base_url() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $dir = str_replace('\\', '/', dirname($script));
    // If called from /webhooks or /admin, climb to app root
    $dir = preg_replace('#/(admin|webhooks)$#', '', $dir);
    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        $dir = '';
    }
    return rtrim($scheme . '://' . $host . $dir, '/');
}

<?php
if (defined('APP_SECURITY_LOADED')) {
    return;
}
define('APP_SECURITY_LOADED', true);

// Data directory kept OUTSIDE the web root so uploaded files
// (which contain NRC and phone numbers) can never be downloaded over HTTP.
if (!defined('APP_DATA_DIR')) {
    define('APP_DATA_DIR', dirname(__DIR__, 3) . '/www/mmName_data');
}
if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', APP_DATA_DIR . '/uploads');
}
if (!defined('LOCK_DIR')) {
    define('LOCK_DIR', APP_DATA_DIR . '/locks');
}

// ---- Secure session handling ----
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? 80) == 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---- Security headers ----
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('X-XSS-Protection: 1; mode=block');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header("Content-Security-Policy: default-src 'self' https://cdn.jsdelivr.net; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net; img-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'");
}

// ---- CSRF protection ----
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify() {
    $sent = $_POST['csrf_token'] ?? ($_GET['csrf'] ?? '');
    $stored = $_SESSION['csrf_token'] ?? '';
    if ($stored === '' || !is_string($sent) || !hash_equals($stored, $sent)) {
        http_response_code(403);
        exit('Invalid or missing CSRF token.');
    }
}

// ---- Guard for diagnostic/admin tools ----
// Access these scripts with:  ?key=<ADMIN_TOKEN>
// Token comes from the APP_ADMIN_TOKEN env var, or config/security.php.
function require_admin_token() {
    $expected = getenv('APP_ADMIN_TOKEN');
    if ($expected === false || $expected === '') {
        $expected = defined('ADMIN_TOKEN') ? ADMIN_TOKEN : '';
    }
    if ($expected === '') {
        http_response_code(403);
        exit('Access denied. No admin token configured.');
    }
    $provided = $_GET['key'] ?? '';
    if (!is_string($provided) || !hash_equals($expected, $provided)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

// ---- Ensure the data directories exist and are usable ----
function ensure_data_dirs() {
    foreach ([APP_DATA_DIR, UPLOAD_DIR, LOCK_DIR] as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            app_error_log("Could not create directory: {$dir}");
        }
    }
}
ensure_data_dirs();

function app_error_log($message) {
    error_log('[mmName] ' . $message);
}

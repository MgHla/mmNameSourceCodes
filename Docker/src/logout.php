<?php
require_once 'includes/security.php';
require_once 'config/database.php';
require_once 'includes/session_manager_db.php';

// CSRF-protect the logout action (token is appended to logout links)
csrf_verify();

try {
    $database = new Database();
    $db = $database->getConnection();
    $sessionManager = new SessionManagerDB($db);
    $sessionManager->releaseLock();
} catch (Exception $e) {
    // Ignore errors on logout
}

$_SESSION = array();

// Expire the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: index.php');
exit();

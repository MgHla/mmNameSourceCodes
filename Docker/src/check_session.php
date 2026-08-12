<?php
require_once 'includes/security.php';

header('Content-Type: application/json');

// Multiple users are allowed concurrently, so the system is always available.
$response = [
    'active' => false,
    'is_current_user' => false,
    'message' => 'System is available'
];

if (isset($_SESSION['user_active'])) {
    $response['active'] = true;
    $response['is_current_user'] = true;
    $response['message'] = 'Your session is active';
}

echo json_encode($response);

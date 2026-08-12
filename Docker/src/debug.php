<?php
require_once 'includes/security.php';
require_once 'config/security.php';
require_admin_token();

echo "<h2>System Debug Information</h2>";

echo "<h3>PHP Version</h3>";
echo "<p>" . phpversion() . "</p>";

echo "<h3>Session Status</h3>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "<p>Session started</p>";
} else {
    echo "<p>Session already active</p>";
}
echo "<p>Session ID: " . session_id() . "</p>";

echo "<h3>Directory Permissions</h3>";
$dirs = ['locks', 'uploads', 'includes', 'config'];
foreach ($dirs as $dir) {
    if (file_exists($dir)) {
        echo "<p style='color:green'>✓ {$dir} exists - Writable: " . 
             (is_writable($dir) ? 'Yes' : 'No') . "</p>";
    } else {
        echo "<p style='color:orange'>⚠ {$dir} does not exist</p>";
    }
}

echo "<h3>Database Connection</h3>";
try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    echo "<p style='color:green'>✓ Database connected successfully</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Database connection failed: " . $e->getMessage() . "</p>";
}

echo "<h3>Server Information</h3>";
echo "<p>Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</p>";
echo "<p>Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "</p>";
echo "<p>Current Script: " . __FILE__ . "</p>";

echo "<h3>Error Log Location</h3>";
echo "<p>" . ini_get('error_log') . "</p>";

echo "<h3>Loaded Extensions</h3>";
$extensions = get_loaded_extensions();
sort($extensions);
echo "<p>" . implode(', ', $extensions) . "</p>";
?>

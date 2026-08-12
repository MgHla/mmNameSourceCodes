<?php
require_once 'includes/security.php';
require_once 'config/security.php';
require_admin_token();

echo "<h1>Setting up permissions</h1>";

$directories = [
    UPLOAD_DIR,
    LOCK_DIR
];

foreach ($directories as $dir) {
    echo "<p>Processing directory: {$dir}</p>";
    
    if (!file_exists($dir)) {
        if (mkdir($dir, 0750, true)) {
            echo "<p style='color:green'>✓ Created directory: {$dir}</p>";
        } else {
            echo "<p style='color:red'>✗ Failed to create directory: {$dir}</p>";
            echo "<p>Please create this directory manually and set permissions to 750</p>";
        }
    } else {
        echo "<p>Directory already exists: {$dir}</p>";
    }
    
    // Check permissions
    if (is_writable($dir)) {
        echo "<p style='color:green'>✓ Directory is writable: {$dir}</p>";
    } else {
        echo "<p style='color:red'>✗ Directory is not writable: {$dir}</p>";
        
        // Try to change permissions
        if (chmod($dir, 0750)) {
            echo "<p style='color:green'>✓ Changed permissions for: {$dir}</p>";
        } else {
            echo "<p style='color:red'>✗ Could not change permissions for: {$dir}</p>";
            echo "<p>Run this command in terminal:</p>";
            echo "<pre>sudo chmod -R 750 " . realpath($dir) . "</pre>";
            echo "<p>Or change owner:</p>";
            echo "<pre>sudo chown -R www-data:www-data " . realpath($dir) . "</pre>";
        }
    }
}

echo "<h2>Current user information:</h2>";
echo "<p>PHP User: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : get_current_user()) . "</p>";
echo "<p>Script Owner: " . get_current_user() . "</p>";
echo "<p>Current Directory: " . getcwd() . "</p>";
?>

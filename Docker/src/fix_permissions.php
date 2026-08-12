<?php
require_once 'includes/security.php';
require_once 'config/security.php';
require_admin_token();

echo "<h1>Permission Fix Tool</h1>";

$projectDir = __DIR__;
$uploadDir = UPLOAD_DIR;
$lockDir = LOCK_DIR;

echo "<h3>Current Information:</h3>";
echo "<p>Project Directory: {$projectDir}</p>";
echo "<p>PHP User: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : get_current_user()) . "</p>";
echo "<p>Web Server User: (usually www-data or apache)</p>";

// Fix upload directory
echo "<h3>Fixing Upload Directory:</h3>";
if (!file_exists($uploadDir)) {
    if (mkdir($uploadDir, 0750, true)) {
        echo "<p style='color:green'>✓ Created uploads directory</p>";
    } else {
        echo "<p style='color:red'>✗ Failed to create uploads directory</p>";
        echo "<p>Run: <code>sudo mkdir -p {$uploadDir}</code></p>";
    }
} else {
    echo "<p>Uploads directory exists</p>";
}

// Set permissions
if (file_exists($uploadDir)) {
    chmod($uploadDir, 0750);
    echo "<p>Permissions: " . substr(sprintf('%o', fileperms($uploadDir)), -4) . "</p>";
    echo "<p>Writable: " . (is_writable($uploadDir) ? 'Yes ✓' : 'No ✗') . "</p>";
}

// Fix lock directory
echo "<h3>Fixing Lock Directory:</h3>";
if (!file_exists($lockDir)) {
    if (mkdir($lockDir, 0750, true)) {
        echo "<p style='color:green'>✓ Created locks directory</p>";
    } else {
        echo "<p style='color:red'>✗ Failed to create locks directory</p>";
    }
} else {
    echo "<p>Locks directory exists</p>";
    // Remove old lock file
    $lockFile = $lockDir . '/session.lock';
    if (file_exists($lockFile)) {
        unlink($lockFile);
        echo "<p style='color:green'>✓ Removed old session lock</p>";
    }
}

if (file_exists($lockDir)) {
    chmod($lockDir, 0750);
    echo "<p>Permissions: " . substr(sprintf('%o', fileperms($lockDir)), -4) . "</p>";
    echo "<p>Writable: " . (is_writable($lockDir) ? 'Yes ✓' : 'No ✗') . "</p>";
}

echo "<h3>Commands to run in terminal if needed:</h3>";
echo "<pre>
sudo chown -R www-data:www-data {$projectDir}
sudo chmod -R 755 {$projectDir}
sudo chmod 750 {$uploadDir}
sudo chmod 750 {$lockDir}
sudo systemctl restart apache2
</pre>";

echo "<p><a href='upload.php' class='btn btn-primary'>Go to Upload Page</a></p>";
?>

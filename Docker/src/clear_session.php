<?php
require_once 'includes/security.php';
require_once 'config/security.php';
require_admin_token();

echo "<h2>Session Cleanup Tool</h2>";

// Clear file-based lock
$lockFile = LOCK_DIR . '/session.lock';
if (file_exists($lockFile)) {
    if (unlink($lockFile)) {
        echo "<p style='color:green'>✓ File lock removed successfully</p>";
    } else {
        echo "<p style='color:red'>✗ Failed to remove file lock</p>";
        echo "<p>Please delete this file manually: " . realpath($lockFile) . "</p>";
    }
} else {
    echo "<p>No file lock found</p>";
}

// Clear database sessions if using database version
try {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if active_sessions table exists
    $stmt = $db->query("SHOW TABLES LIKE 'active_sessions'");
    if ($stmt->rowCount() > 0) {
        // Clear all active sessions
        $db->exec("TRUNCATE TABLE active_sessions");
        echo "<p style='color:green'>✓ Database sessions cleared</p>";
    } else {
        echo "<p>No database sessions table found</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Database error: " . $e->getMessage() . "</p>";
}

// Clear PHP sessions
$sessionPath = session_save_path();
echo "<p>Session save path: " . $sessionPath . "</p>";

if (is_dir($sessionPath)) {
    $files = glob($sessionPath . '/sess_*');
    $count = count($files);
    echo "<p>Found {$count} session files</p>";
    
    // Delete old session files (older than 30 minutes)
    $deleted = 0;
    foreach ($files as $file) {
        if (filemtime($file) < time() - 1800) {
            if (unlink($file)) {
                $deleted++;
            }
        }
    }
    echo "<p style='color:green'>✓ Deleted {$deleted} expired session files</p>";
}

echo "<br>";
echo "<a href='index.php' class='btn btn-primary'>Go to Application</a>";
echo " ";
echo "<a href='debug.php' class='btn btn-secondary'>System Debug</a>";
?>

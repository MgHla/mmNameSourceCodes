<?php
class SessionManager {
    private $db;
    private $lockFile = 'session.lock';
    private $lockDir = 'locks';
    private $sessionTimeout = 1800; // 30 minutes
    
    public function __construct($db) {
        $this->db = $db;
        $this->initializeLockDirectory();
    }
    
    private function initializeLockDirectory() {
        if (!file_exists($this->lockDir)) {
            @mkdir($this->lockDir, 0755, true);
        }
        
        // Try alternative directory if main one fails
        if (!is_writable($this->lockDir)) {
            $this->lockDir = sys_get_temp_dir() . '/nrc_locks';
            if (!file_exists($this->lockDir)) {
                @mkdir($this->lockDir, 0755, true);
            }
        }
    }
    
    public function checkConcurrency() {
        // Multiple users are allowed concurrently. Only refresh the lock
        // timestamp for housekeeping; never block another user.
        $lockPath = $this->lockDir . '/' . $this->lockFile;
        $this->updateLock($lockPath);
        return true;
    }
    
    private function createLock($path) {
        $lockData = [
            'session_id' => session_id(),
            'timestamp' => time(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        return $this->writeLockFile($path, $lockData);
    }
    
    private function updateLock($path) {
        $lockData = [
            'session_id' => session_id(),
            'timestamp' => time(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        return $this->writeLockFile($path, $lockData);
    }
    
    public function forceRemoveLock($path = null) {
        if ($path === null) {
            $path = $this->lockDir . '/' . $this->lockFile;
        }
        
        if (file_exists($path)) {
            return @unlink($path);
        }
        return true;
    }
    
    private function readLockFile($path) {
        if (!file_exists($path) || !is_readable($path)) {
            return null;
        }
        
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }
        
        $data = json_decode($contents, true);
        return is_array($data) ? $data : null;
    }
    
    private function writeLockFile($path, $data) {
        $json = json_encode($data);
        $result = @file_put_contents($path, $json, LOCK_EX);
        
        if ($result === false) {
            error_log("Failed to write lock file: " . $path);
            return false;
        }
        
        @chmod($path, 0644);
        return true;
    }
    
    private function isSessionValid($sessionId) {
        // Check if session file exists and is recent
        $sessionPath = session_save_path();
        if (!$sessionPath) {
            $sessionPath = sys_get_temp_dir();
        }
        
        $sessionFile = $sessionPath . '/sess_' . $sessionId;
        
        if (file_exists($sessionFile)) {
            $lastModified = filemtime($sessionFile);
            return (time() - $lastModified) < $this->sessionTimeout;
        }
        
        return false;
    }
    
    public function releaseLock() {
        $lockPath = $this->lockDir . '/' . $this->lockFile;
        
        if (file_exists($lockPath)) {
            $lockData = $this->readLockFile($lockPath);
            
            if ($lockData && ($lockData['session_id'] ?? '') === session_id()) {
                return @unlink($lockPath);
            }
            
            // If lock exists but session doesn't match, force remove
            return @unlink($lockPath);
        }
        return true;
    }
    
    public function isCurrentUser() {
        $lockPath = $this->lockDir . '/' . $this->lockFile;
        
        if (file_exists($lockPath)) {
            $lockData = $this->readLockFile($lockPath);
            if ($lockData) {
                // Check by session ID OR IP address (for same user)
                return ($lockData['session_id'] ?? '') === session_id() ||
                       ($lockData['ip_address'] ?? '') === ($_SERVER['REMOTE_ADDR'] ?? '');
            }
        }
        return false;
    }
    
    public function getActiveSessionInfo() {
        $lockPath = $this->lockDir . '/' . $this->lockFile;
        
        if (file_exists($lockPath)) {
            return $this->readLockFile($lockPath);
        }
        return null;
    }
}
?>

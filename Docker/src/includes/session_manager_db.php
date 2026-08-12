<?php
class SessionManagerDB {
    private $db;
    private $tableName = 'active_sessions';
    private $sessionTimeout = 1800; // 30 minutes
    
    public function __construct($db) {
        if (!is_object($db)) {
            $this->db = null;
            return;
        }
        $this->db = $db;
        $this->createSessionTable();
    }
    
    private function createSessionTable() {
        if (!$this->db) {
            return;
        }
        try {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            $this->db->exec($sql);
        } catch (PDOException $e) {
            error_log("Failed to create sessions table: " . $e->getMessage());
        }
    }
    
    public function checkConcurrency() {
        if (!$this->db) {
            return true; // No database available - allow access
        }
        try {
            // Clean expired sessions (housekeeping only, never blocks anyone)
            $this->cleanAllExpiredSessions();
            
            // Multiple users are allowed concurrently. Just record the
            // current session for activity tracking.
            $this->upsertSession();
            return true;
            
        } catch (PDOException $e) {
            error_log("Concurrency check failed: " . $e->getMessage());
            return true; // Allow access on error
        }
    }
    
    private function cleanAllExpiredSessions() {
        try {
            $stmt = $this->db->prepare(
                "DELETE FROM {$this->tableName} 
                 WHERE last_activity < DATE_SUB(NOW(), INTERVAL {$this->sessionTimeout} SECOND)"
            );
            $stmt->execute();
            
            // If table is empty but no timeout, clean all (safety measure)
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM {$this->tableName}");
            $stmt->execute();
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($count > 0) {
                // If there are very old sessions, force clean
                $stmt = $this->db->prepare(
                    "DELETE FROM {$this->tableName} 
                     WHERE last_activity < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
                );
                $stmt->execute();
            }
        } catch (PDOException $e) {
            error_log("Clean expired sessions failed: " . $e->getMessage());
        }
    }
    
    private function upsertSession() {
        try {
            // Try to insert, update if exists
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->tableName} (session_id, ip_address, user_agent) 
                 VALUES (:session_id, :ip_address, :user_agent)
                 ON DUPLICATE KEY UPDATE 
                    session_id = VALUES(session_id),
                    last_activity = NOW()"
            );
            
            $stmt->execute([
                ':session_id' => session_id(),
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (PDOException $e) {
            error_log("Upsert session failed: " . $e->getMessage());
            
            // Alternative: Delete and insert
            try {
                $this->db->exec("DELETE FROM {$this->tableName}");
                $stmt = $this->db->prepare(
                    "INSERT INTO {$this->tableName} (session_id, ip_address, user_agent) 
                     VALUES (:session_id, :ip_address, :user_agent)"
                );
                $stmt->execute([
                    ':session_id' => session_id(),
                    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]);
            } catch (PDOException $e2) {
                error_log("Alternative insert failed: " . $e2->getMessage());
            }
        }
    }
    
    public function releaseLock() {
        if (!$this->db) {
            return true;
        }
        try {
            // Delete only the current session, never another user's session
            $stmt = $this->db->prepare(
                "DELETE FROM {$this->tableName} WHERE session_id = :session_id"
            );
            $stmt->execute([':session_id' => session_id()]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Release lock failed: " . $e->getMessage());
            return true;
        }
    }
    
    public function isCurrentUser() {
        if (!$this->db) {
            return false;
        }
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as count FROM {$this->tableName} 
                 WHERE session_id = :session_id"
            );
            $stmt->execute([':session_id' => session_id()]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function getActiveSessionInfo() {
        if (!$this->db) {
            return null;
        }
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->tableName} LIMIT 1");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return null;
        }
    }
}
?>

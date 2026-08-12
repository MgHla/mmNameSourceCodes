<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // Prefer environment variables; fall back to previous values.
        $this->host = getenv('DB_HOST') !== false ? getenv('DB_HOST') : 'localhost';
        $this->db_name = getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'mmName';
        $this->username = getenv('DB_USER') !== false ? getenv('DB_USER') : 'mmname';
        $this->password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : 'P@ssword243';
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                ]
            );
        } catch (PDOException $exception) {
            // Never echo connection details to the client.
            error_log("Database connection error: " . $exception->getMessage());
            $this->conn = null;
        }
        return $this->conn;
    }
}

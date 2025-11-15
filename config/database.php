<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_penyewaan'); 

class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    private $conn;
    private $error;
    
    /**
     * Koneksi ke database
     */
    public function connect() {
        $this->conn = null;
        
        try {
            $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->dbname);
            
            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }
            
            $this->conn->set_charset("utf8mb4");
            
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            echo "Database Connection Error: " . $this->error;
        }
        
        return $this->conn;
    }
    
    /**
     * Tutup koneksi database
     */
    public function close() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
    
    /**
     * Escape string untuk keamanan
     */
    public function escape($value) {
        return $this->conn->real_escape_string($value);
    }
}

function getConnection() {
    $database = new Database();
    return $database->connect();
}
?>
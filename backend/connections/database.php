<?php
class Database {
    private $host = "localhost"; 
    private $username = "root";  
    private $password = "";      
    private $database = "barangay_request_system"; 
    private $conn;
    private $inTransaction = false;
    
    public function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->database}",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    public function beginTransaction() {
        $this->inTransaction = true;
        return $this->conn->beginTransaction();
    }
    
    public function commit() {
        $this->inTransaction = false;
        return $this->conn->commit();
    }
    
    public function rollback() {
        $this->inTransaction = false;
        return $this->conn->rollBack();
    }
    
    public function inTransaction() {
        return $this->inTransaction;
    }
    
    public function execute($query, $params = []) {
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }
    
    public function fetchOne($query, $params = []) {
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function fetchAll($query, $params = []) {
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function insert($query, $params = []) {
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $this->conn->lastInsertId();
    }
    
    public function closeConnection() {
        $this->conn = null;
    }
}
?>
<?php

class Database
{
    private $host;
    private $port;
    private $dbname;
    private $username;
    private $password;
    private $conn;

    public function __construct()
    {
        // Read .env file
        $envFile = dirname(__DIR__) . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $env = [];
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $env[trim($key)] = trim($value);
                }
            }

            // Set database connection parameters
            $this->host = $env['DB_HOST'] ?? '127.0.0.1';
            $this->port = $env['DB_PORT'] ?? '3306';
            $this->dbname = $env['DB_DATABASE'] ?? '';
            $this->username = $env['DB_USERNAME'] ?? '';
            $this->password = $env['DB_PASSWORD'] ?? '';
        }
    }

    public function connect()
    {
        $this->conn = null;

        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbname};charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->exec("set names utf8");
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
        }

        return $this->conn;
    }

    public function getConnection()
    {
        return $this->connect();
    }
}
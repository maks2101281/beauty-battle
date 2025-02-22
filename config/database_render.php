<?php
require_once __DIR__ . '/env.php';

class Database {
    private static $instance = null;
    private $pdo;
    private $inTransaction = false;

    private function __construct() {
        try {
            // Получаем параметры подключения из переменных окружения
            $host = getenv('PGHOST') ?: 'localhost';
            $port = getenv('PGPORT') ?: '5432';
            $database = getenv('PGDATABASE') ?: 'postgres';
            $user = getenv('PGUSER') ?: 'postgres';
            $password = getenv('PGPASSWORD') ?: '';

            // Формируем DSN
            $dsn = "pgsql:host={$host};port={$port};dbname={$database}";

            // Опции подключения
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true
            ];

            // Создаем подключение
            $this->pdo = new PDO($dsn, $user, $password, $options);

            // Устанавливаем временную зону
            $timezone = getenv('TZ') ?: 'UTC';
            $this->pdo->exec("SET timezone TO '{$timezone}'");

            // Устанавливаем схему поиска
            $this->pdo->exec('SET search_path TO public');

            // Проверяем подключение
            $this->pdo->query('SELECT 1');

        } catch (PDOException $e) {
            // Логируем ошибку
            error_log('Database connection error: ' . $e->getMessage());
            throw new Exception('Ошибка подключения к базе данных');
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function beginTransaction() {
        if (!$this->inTransaction) {
            $this->pdo->beginTransaction();
            $this->inTransaction = true;
        }
    }

    public function commit() {
        if ($this->inTransaction) {
            $this->pdo->commit();
            $this->inTransaction = false;
        }
    }

    public function rollback() {
        if ($this->inTransaction) {
            $this->pdo->rollBack();
            $this->inTransaction = false;
        }
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('Database query error: ' . $e->getMessage());
            throw new Exception('Ошибка выполнения запроса');
        }
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function execute($sql, $params = []) {
        return $this->query($sql, $params)->rowCount();
    }

    public function lastInsertId($name = null) {
        return $this->pdo->lastInsertId($name);
    }

    public function quote($value) {
        return $this->pdo->quote($value);
    }
}

// Создаем экземпляр подключения к базе данных
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
} catch (Exception $e) {
    error_log($e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Ошибка подключения к базе данных']);
    exit;
}
<?php
require_once __DIR__ . '/env.php';

try {
    // Формируем строку подключения
    $host = getenv('DB_HOST') ?: Env::get('DB_HOST');
    $dbname = getenv('DB_NAME') ?: Env::get('DB_NAME');
    $user = getenv('DB_USER') ?: Env::get('DB_USER');
    $password = getenv('DB_PASSWORD') ?: Env::get('DB_PASSWORD');

    // Добавляем полное доменное имя для хоста
    if (strpos($host, '.') === false) {
        $host .= '.oregon-postgres.render.com';
    }

    // Создаем DSN с явным указанием порта и SSL режима
    $dsn = "pgsql:host={$host};dbname={$dbname};sslmode=require";
    
    if (Env::get('APP_DEBUG', false)) {
        error_log("Connecting to database with DSN: " . str_replace($password, '***', $dsn));
    }
    
    $pdo = new PDO(
        $dsn,
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // Устанавливаем таймзону
    $pdo->exec("SET timezone = 'Europe/Moscow'");
    
} catch (PDOException $e) {
    if (Env::get('APP_DEBUG', false)) {
        error_log("Database connection error: " . $e->getMessage());
        throw $e;
    }
    die('Database connection failed: ' . $e->getMessage());
}
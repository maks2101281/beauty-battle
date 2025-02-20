<?php
require_once __DIR__ . '/env.php';

try {
    // Формируем строку подключения
    $host = Env::get('DB_HOST');
    $dbname = Env::get('DB_NAME');
    $user = Env::get('DB_USER');
    $password = Env::get('DB_PASSWORD');

    // Создаем DSN с явным указанием порта и SSL режима
    $dsn = "pgsql:host={$host};dbname={$dbname};sslmode=require";
    
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
        throw $e;
    }
    die('Database connection failed: ' . $e->getMessage());
}
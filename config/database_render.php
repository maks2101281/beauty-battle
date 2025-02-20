<?php
require_once __DIR__ . '/env.php';

try {
    $pdo = new PDO(
        "pgsql:host=" . Env::get('DB_HOST') . 
        ";dbname=" . Env::get('DB_NAME') . 
        ";sslmode=require",
        Env::get('DB_USER'),
        Env::get('DB_PASSWORD'),
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
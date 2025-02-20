<?php
require_once __DIR__ . '/env.php';

try {
    $pdo = new PDO(
        "pgsql:host=" . Env::get('DB_HOST') . 
        ";dbname=" . Env::get('DB_NAME') . 
        ";user=" . Env::get('DB_USER') . 
        ";password=" . Env::get('DB_PASSWORD')
    );
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    if (Env::get('APP_DEBUG', false)) {
        throw $e;
    }
    die('Ошибка подключения к базе данных');
} 
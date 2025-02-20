<?php
require_once __DIR__ . '/env.php';

try {
    $pdo = new PDO(
        "mysql:host=" . Env::get('DB_HOST') . 
        ";dbname=" . Env::get('DB_NAME'),
        Env::get('DB_USER'),
        Env::get('DB_PASSWORD'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
} catch (PDOException $e) {
    if (Env::get('APP_DEBUG', false)) {
        throw $e;
    }
    die('Ошибка подключения к базе данных');
} 
<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../classes/Logger.php';

try {
    // Подключаемся к базе данных
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
    
    echo "Connected to database successfully\n";
    
    // Читаем SQL файл
    $sql = file_get_contents(__DIR__ . '/../api/database/schema_pg.sql');
    
    // Разделяем на отдельные запросы
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    // Выполняем каждый запрос
    foreach ($queries as $query) {
        if (!empty($query)) {
            $pdo->exec($query);
            echo "Executed query successfully\n";
        }
    }
    
    // Устанавливаем таймзону
    $pdo->exec("SET timezone = 'Europe/Moscow'");
    
    echo "Database initialized successfully!\n";
    
    // Логируем успешную инициализацию
    Logger::info('Database initialized successfully on Render');
    
} catch (PDOException $e) {
    echo "Database initialization failed: " . $e->getMessage() . "\n";
    Logger::error('Database initialization failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
} 
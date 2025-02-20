<?php
require_once 'config/database.php';

try {
    // Читаем SQL файл
    $sql = file_get_contents('api/database/schema.sql');
    
    // Разделяем на отдельные запросы
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    // Выполняем каждый запрос
    foreach ($queries as $query) {
        if (!empty($query)) {
            $pdo->exec($query);
        }
    }
    
    echo "База данных успешно импортирована!\n";
    
} catch (PDOException $e) {
    die("Ошибка импорта базы данных: " . $e->getMessage());
} 
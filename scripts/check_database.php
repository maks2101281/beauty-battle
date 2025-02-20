<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database_render.php';

echo "Проверка структуры базы данных...\n\n";

try {
    // Проверяем существование таблиц
    $tables = [
        'telegram_users',
        'verification_codes',
        'auth_tokens',
        'auth_attempts',
        'contestants',
        'comments',
        'comment_likes',
        'achievements',
        'user_achievements',
        'voting_settings',
        'tournaments',
        'tournament_rounds',
        'matches',
        'match_votes'
    ];

    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT EXISTS (
            SELECT FROM information_schema.tables 
            WHERE table_name = '{$table}'
        )");
        $exists = $stmt->fetchColumn();
        
        echo "Таблица {$table}: " . ($exists ? "OK" : "ОТСУТСТВУЕТ") . "\n";
        
        if ($exists) {
            // Проверяем структуру таблицы
            $stmt = $pdo->query("SELECT column_name, data_type, character_maximum_length
                FROM information_schema.columns
                WHERE table_name = '{$table}'");
            $columns = $stmt->fetchAll();
            
            echo "  Колонки:\n";
            foreach ($columns as $column) {
                echo "    - {$column['column_name']} ({$column['data_type']})\n";
            }
            echo "\n";
        }
    }

    // Проверяем индексы
    echo "\nПроверка индексов...\n";
    $stmt = $pdo->query("SELECT tablename, indexname FROM pg_indexes WHERE schemaname = 'public'");
    $indexes = $stmt->fetchAll();
    foreach ($indexes as $index) {
        echo "Индекс {$index['indexname']} для таблицы {$index['tablename']}\n";
    }

    // Проверяем триггеры
    echo "\nПроверка триггеров...\n";
    $stmt = $pdo->query("SELECT tgname FROM pg_trigger WHERE tgisinternal = false");
    $triggers = $stmt->fetchAll();
    foreach ($triggers as $trigger) {
        echo "Триггер: {$trigger['tgname']}\n";
    }

    echo "\nПроверка завершена успешно!\n";

} catch (Exception $e) {
    echo "Ошибка при проверке базы данных: " . $e->getMessage() . "\n";
} 
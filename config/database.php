<?php
require_once __DIR__ . '/env.php';

try {
    // Для локальной разработки используем SQLite
    if (Env::get('APP_ENV') === 'development' || !extension_loaded('pdo_pgsql')) {
        $dbPath = __DIR__ . '/../database/local.sqlite';
        $dbDir = dirname($dbPath);
        
        // Создаем директорию для базы данных если её нет
        if (!file_exists($dbDir)) {
            mkdir($dbDir, 0777, true);
        }
        
        $pdo = new PDO("sqlite:{$dbPath}");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Создаем необходимые таблицы если их нет
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS submissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                media_type TEXT NOT NULL,
                media_path TEXT NOT NULL,
                thumbnail_path TEXT,
                social_link TEXT,
                user_id INTEGER,
                status TEXT DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
    } else {
        // Для продакшена используем PostgreSQL
        $host = getenv('DB_HOST') ?: Env::get('DB_HOST');
        $dbname = getenv('DB_NAME') ?: Env::get('DB_NAME');
        $user = getenv('DB_USER') ?: Env::get('DB_USER');
        $password = getenv('DB_PASSWORD') ?: Env::get('DB_PASSWORD');

        if (strpos($host, '.') === false) {
            $host .= '.oregon-postgres.render.com';
        }

        $pdo = new PDO(
            "pgsql:host={$host};port=5432;dbname={$dbname};sslmode=require",
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    }
} catch (PDOException $e) {
    if (Env::get('APP_DEBUG', false)) {
        throw $e;
    }
    die('Database connection failed');
} 
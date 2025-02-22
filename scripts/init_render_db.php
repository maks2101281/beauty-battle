<?php
// Определяем корневую директорию проекта
define('ROOT_DIR', realpath(__DIR__ . '/..'));

// Подключаем зависимости
require_once ROOT_DIR . '/config/env.php';
require_once ROOT_DIR . '/classes/Logger.php';

try {
    echo "Starting database initialization...\n";
    
    // Получаем параметры подключения
    $host = getenv('DB_HOST') ?: Env::get('DB_HOST');
    $dbname = getenv('DB_NAME') ?: Env::get('DB_NAME');
    $user = getenv('DB_USER') ?: Env::get('DB_USER');
    $password = getenv('DB_PASSWORD') ?: Env::get('DB_PASSWORD');

    echo "Database parameters:\n";
    echo "Host: {$host}\n";
    echo "Database: {$dbname}\n";
    echo "User: {$user}\n";

    // Добавляем полное доменное имя для хоста
    if (strpos($host, '.') === false) {
        $host .= '.oregon-postgres.render.com';
    }

    // Путь к файлу сертификата
    $certFile = '/var/www/.postgresql/root.crt';
    echo "Certificate path: {$certFile}\n";
    echo "Certificate exists: " . (file_exists($certFile) ? "Yes" : "No") . "\n";

    // Формируем DSN
    $dsn = "pgsql:host={$host};port=5432;dbname={$dbname};sslmode=require";
    echo "Connecting to database...\n";

    // Подключаемся к базе данных
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    echo "Connected successfully!\n";
    
    // Читаем SQL файл
    $schemaFile = ROOT_DIR . '/api/database/schema_pg.sql';
    echo "Reading schema from: {$schemaFile}\n";
    
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found: {$schemaFile}");
    }
    
    $sql = file_get_contents($schemaFile);
    
    // Разделяем на отдельные запросы
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    echo "Executing queries...\n";
    // Выполняем каждый запрос
    foreach ($queries as $index => $query) {
        if (!empty($query)) {
            try {
                $pdo->exec($query);
                echo "Query " . ($index + 1) . " executed successfully\n";
            } catch (PDOException $e) {
                echo "Error executing query " . ($index + 1) . ": " . $e->getMessage() . "\n";
                throw $e;
            }
        }
    }
    
    // Устанавливаем таймзону
    $pdo->exec("SET timezone = 'Europe/Moscow'");
    
    echo "Database initialization completed successfully!\n";
    
    // Логируем успешную инициализацию
    Logger::info('Database initialized successfully on Render');
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
} 
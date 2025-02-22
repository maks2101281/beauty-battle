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
        $host .= '.frankfurt-postgres.render.com';
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
        'rounds',
        'matches',
        'votes',
        'ip_votes',
        'admins'
    ];

    $existingTables = [];
    foreach ($tables as $table) {
        $stmt = $pdo->query("
            SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_name = '{$table}'
            )
        ");
        if ($stmt->fetchColumn()) {
            $existingTables[] = $table;
            echo "Found existing table: {$table}\n";
        }
    }

    // Читаем SQL файл
    $schemaFile = ROOT_DIR . '/api/database/schema_pg.sql';
    echo "Reading schema from: {$schemaFile}\n";
    
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found: {$schemaFile}");
    }
    
    $sql = file_get_contents($schemaFile);
    
    // Разделяем на отдельные запросы
    $queries = array_filter(
        array_map(
            'trim',
            preg_split("/;\s*\n/", $sql)
        )
    );
    
    echo "Starting database setup...\n";
    
    // Начинаем транзакцию
    $pdo->beginTransaction();
    
    try {
        // Устанавливаем таймзону
        $pdo->exec("SET timezone TO 'Europe/Moscow'");
        
        foreach ($queries as $index => $query) {
            // Пропускаем пустые запросы
            if (empty($query)) continue;
            
            // Пропускаем создание существующих таблиц
            $skipQuery = false;
            foreach ($existingTables as $table) {
                if (preg_match("/CREATE TABLE\s+{$table}/i", $query)) {
                    echo "Skipping creation of existing table: {$table}\n";
                    $skipQuery = true;
                    break;
                }
            }
            if ($skipQuery) continue;

            // Выполняем запрос
            try {
                $pdo->exec($query);
                echo "Query " . ($index + 1) . " executed successfully\n";
            } catch (PDOException $e) {
                // Пропускаем ошибки о существующих объектах
                if ($e->getCode() == '42P07' || // Duplicate table
                    $e->getCode() == '42710' || // Duplicate index
                    $e->getCode() == '42P16' || // Duplicate constraint
                    $e->getCode() == '42P13'    // Duplicate function
                ) {
                    echo "Warning: " . $e->getMessage() . "\n";
                    continue;
                }
                throw $e;
            }
        }

        // Проверяем и добавляем начальные данные
        echo "Checking initial data...\n";

        // Проверяем наличие настроек голосования
        $stmt = $pdo->query("SELECT COUNT(*) FROM voting_settings");
        if ($stmt->fetchColumn() == 0) {
            echo "Adding default voting settings...\n";
            $pdo->exec("
                INSERT INTO voting_settings (
                    votes_to_win, 
                    max_active_matches, 
                    votes_per_ip_per_day
                ) VALUES (10, 5, 20)
            ");
        }

        // Проверяем наличие администратора
        $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
        if ($stmt->fetchColumn() == 0) {
            echo "Adding default admin user...\n";
            $pdo->exec("
                INSERT INTO admins (username, password_hash)
                VALUES ('admin', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
            ");
        }

        // Проверяем наличие первого раунда
        $stmt = $pdo->query("SELECT COUNT(*) FROM rounds");
        if ($stmt->fetchColumn() == 0) {
            echo "Creating first round...\n";
            $pdo->exec("
                INSERT INTO rounds (number, status)
                VALUES (1, 'active')
            ");
        }

        $pdo->commit();
        echo "Database initialization completed successfully!\n";
        
        // Проверяем все таблицы после инициализации
        echo "Verifying database structure...\n";
        foreach ($tables as $table) {
            $stmt = $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
            if ($stmt) {
                echo "Table {$table} verified successfully\n";
            } else {
                throw new Exception("Table {$table} verification failed");
            }
        }
        
        // Логируем успешную инициализацию
        Logger::info('Database initialized successfully on Render', [
            'tables_count' => count($tables),
            'existing_tables' => count($existingTables)
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    Logger::error('Database initialization failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
} 
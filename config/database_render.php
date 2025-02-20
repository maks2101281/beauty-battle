<?php
require_once __DIR__ . '/env.php';

try {
    // Формируем строку подключения
    $host = getenv('DB_HOST') ?: Env::get('DB_HOST');
    $dbname = getenv('DB_NAME') ?: Env::get('DB_NAME');
    $user = getenv('DB_USER') ?: Env::get('DB_USER');
    $password = getenv('DB_PASSWORD') ?: Env::get('DB_PASSWORD');

    // Добавляем полное доменное имя для хоста
    if (strpos($host, '.') === false) {
        $host .= '.oregon-postgres.render.com';
    }

    // Создаем DSN с расширенными параметрами подключения
    $dsn = sprintf(
        "pgsql:host=%s;port=5432;dbname=%s;sslmode=verify-ca;sslcert=/etc/ssl/certs/ca-certificates.crt",
        $host,
        $dbname
    );
    
    if (Env::get('APP_DEBUG', false)) {
        error_log("Connecting to database with DSN: " . str_replace($password, '***', $dsn));
        error_log("SSL Certificate path exists: " . (file_exists('/etc/ssl/certs/ca-certificates.crt') ? 'Yes' : 'No'));
    }
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 60, // Увеличиваем таймаут до 60 секунд
        PDO::ATTR_PERSISTENT => true // Используем постоянное соединение
    ];
    
    // Проверяем наличие SSL сертификата
    if (!file_exists('/etc/ssl/certs/ca-certificates.crt')) {
        // Если сертификата нет, пробуем альтернативные пути
        $altPaths = [
            '/etc/ssl/certs/ca-bundle.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/etc/ssl/cert.pem'
        ];
        
        foreach ($altPaths as $path) {
            if (file_exists($path)) {
                $dsn = sprintf(
                    "pgsql:host=%s;port=5432;dbname=%s;sslmode=verify-ca;sslcert=%s",
                    $host,
                    $dbname,
                    $path
                );
                break;
            }
        }
    }
    
    $pdo = new PDO($dsn, $user, $password, $options);
    
    // Устанавливаем таймзону
    $pdo->exec("SET timezone = 'Europe/Moscow'");
    
} catch (PDOException $e) {
    if (Env::get('APP_DEBUG', false)) {
        error_log("Database connection error: " . $e->getMessage());
        error_log("Connection details: host={$host}, dbname={$dbname}, user={$user}");
        error_log("DSN: " . str_replace($password, '***', $dsn));
        throw $e;
    }
    die('Database connection failed: ' . $e->getMessage());
}
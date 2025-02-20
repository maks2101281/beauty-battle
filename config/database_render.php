<?php
require_once __DIR__ . '/env.php';

try {
    // Получаем параметры подключения
    $host = getenv('DB_HOST') ?: Env::get('DB_HOST');
    $dbname = getenv('DB_NAME') ?: Env::get('DB_NAME');
    $user = getenv('DB_USER') ?: Env::get('DB_USER');
    $password = getenv('DB_PASSWORD') ?: Env::get('DB_PASSWORD');

    // Добавляем полное доменное имя для хоста
    if (strpos($host, '.') === false) {
        $host .= '.oregon-postgres.render.com';
    }

    // Путь к файлу сертификата
    $certFile = '/var/www/.postgresql/root.crt';

    // Проверяем наличие сертификата
    if (file_exists($certFile)) {
        // Используем verify-full с указанием пути к сертификату
        $dsn = sprintf(
            "pgsql:host=%s;port=5432;dbname=%s;sslmode=verify-full;sslcert=%s",
            $host,
            $dbname,
            $certFile
        );
    } else {
        // Если сертификат не найден, используем режим require без проверки
        $dsn = sprintf(
            "pgsql:host=%s;port=5432;dbname=%s;sslmode=require",
            $host,
            $dbname
        );
    }

    // Настройки PDO
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => true
    ];

    // Создаем подключение
    $pdo = new PDO($dsn, $user, $password, $options);

    // Устанавливаем таймзону
    $pdo->exec("SET timezone = 'Europe/Moscow'");

} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    error_log("Connection details: host={$host}, dbname={$dbname}, user={$user}");
    error_log("DSN: " . str_replace($password, '***', $dsn));
    error_log("Certificate exists: " . (file_exists($certFile) ? 'Yes' : 'No'));
    throw $e;
}
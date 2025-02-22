<?php
require_once __DIR__ . '/env.php';

try {
    // Получаем параметры подключения
    $host = getenv('DB_HOST');
    if (empty($host)) {
        throw new Exception('DB_HOST не установлен');
    }
    
    $dbname = getenv('DB_NAME');
    if (empty($dbname)) {
        throw new Exception('DB_NAME не установлен');
    }
    
    $user = getenv('DB_USER');
    if (empty($user)) {
        throw new Exception('DB_USER не установлен');
    }
    
    $password = getenv('DB_PASSWORD');
    if (empty($password)) {
        throw new Exception('DB_PASSWORD не установлен');
    }

    // Добавляем полное доменное имя для хоста
    if (strpos($host, '.') === false) {
        $host .= '.frankfurt-postgres.render.com';
    }

    // Путь к файлу сертификата
    $certDir = '/var/www/.postgresql';
    $certFile = $certDir . '/root.crt';

    // Создаем директорию для сертификата если её нет
    if (!file_exists($certDir)) {
        mkdir($certDir, 0755, true);
    }

    // Копируем сертификат если его нет
    if (!file_exists($certFile) && file_exists('/etc/ssl/certs/ca-certificates.crt')) {
        copy('/etc/ssl/certs/ca-certificates.crt', $certFile);
        chmod($certFile, 0644);
    }

    // Формируем DSN
    $dsn = sprintf(
        "pgsql:host=%s;port=5432;dbname=%s;sslmode=require",
        $host,
        $dbname
    );

    // Настройки PDO
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];

    // Создаем подключение
    $pdo = new PDO($dsn, $user, $password, $options);

    // Устанавливаем таймзону
    $pdo->exec("SET timezone = 'Europe/Moscow'");

} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    error_log("Connection details: host={$host}, dbname={$dbname}, user={$user}");
    error_log("DSN: " . str_replace($password, '***', $dsn));
    
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Ошибка подключения к базе данных'
        ]);
        exit;
    } else {
        throw $e;
    }
}
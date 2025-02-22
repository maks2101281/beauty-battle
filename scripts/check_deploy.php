<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../classes/Logger.php';

echo "Проверка готовности к деплою Beauty Battle\n";
echo "=====================================\n\n";

$status = [
    'success' => true,
    'checks' => []
];

function addCheck($name, $result, $details = null) {
    global $status;
    $status['checks'][$name] = [
        'status' => $result ? 'OK' : 'ERROR',
        'details' => $details
    ];
    if (!$result) {
        $status['success'] = false;
    }
}

try {
    // 1. Проверка файловой структуры
    echo "1. Проверка файловой структуры:\n";
    $required_files = [
        'Dockerfile',
        'render.yaml',
        'scripts/docker-entrypoint.sh',
        'public/index.php',
        'public/health.php',
        'config/database_render.php',
        'api/database/schema_pg.sql'
    ];
    
    foreach ($required_files as $file) {
        $exists = file_exists(__DIR__ . '/../' . $file);
        echo "   {$file}: " . ($exists ? "OK" : "ОТСУТСТВУЕТ") . "\n";
        addCheck("file_{$file}", $exists);
    }
    echo "\n";

    // 2. Проверка прав доступа
    echo "2. Проверка прав доступа:\n";
    $directories = [
        'public/uploads',
        'public/uploads/photos',
        'public/uploads/videos',
        'public/uploads/thumbnails',
        'cache',
        'logs'
    ];
    
    foreach ($directories as $dir) {
        $fullPath = __DIR__ . '/../' . $dir;
        if (!file_exists($fullPath)) {
            mkdir($fullPath, 0755, true);
        }
        $writable = is_writable($fullPath);
        echo "   {$dir}: " . ($writable ? "OK" : "НЕТ ПРАВ НА ЗАПИСЬ") . "\n";
        addCheck("dir_{$dir}", $writable);
    }
    echo "\n";

    // 3. Проверка конфигурации
    echo "3. Проверка конфигурации:\n";
    $required_vars = [
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
        'DB_PASSWORD',
        'APP_URL',
        'APP_ENV',
        'TELEGRAM_BOT_TOKEN'
    ];
    
    foreach ($required_vars as $var) {
        $value = Env::get($var);
        echo "   {$var}: " . ($value ? "OK" : "ОТСУТСТВУЕТ") . "\n";
        addCheck("env_{$var}", !empty($value));
    }
    echo "\n";

    // 4. Проверка PHP расширений
    echo "4. Проверка PHP расширений:\n";
    $required_extensions = [
        'pdo',
        'pdo_pgsql',
        'gd',
        'json',
        'curl',
        'fileinfo',
        'exif'
    ];
    
    foreach ($required_extensions as $ext) {
        $loaded = extension_loaded($ext);
        echo "   {$ext}: " . ($loaded ? "OK" : "ОТСУТСТВУЕТ") . "\n";
        addCheck("ext_{$ext}", $loaded);
    }
    echo "\n";

    // 5. Проверка настроек PHP
    echo "5. Проверка настроек PHP:\n";
    $settings = [
        'upload_max_filesize' => '10M',
        'post_max_size' => '10M',
        'memory_limit' => '256M',
        'max_execution_time' => '60'
    ];
    
    foreach ($settings as $key => $expected) {
        $actual = ini_get($key);
        echo "   {$key}: {$actual} (ожидается: {$expected})\n";
        addCheck("php_{$key}", $actual >= $expected);
    }
    echo "\n";

    // 6. Проверка SSL сертификата
    echo "6. Проверка SSL сертификата:\n";
    $cert_file = '/var/www/.postgresql/root.crt';
    $cert_exists = file_exists($cert_file);
    echo "   Сертификат: " . ($cert_exists ? "OK" : "ОТСУТСТВУЕТ") . "\n";
    addCheck("ssl_cert", $cert_exists);
    echo "\n";

    // Выводим итоговый статус
    echo "Итоговый статус: " . ($status['success'] ? "ГОТОВО К ДЕПЛОЮ" : "ТРЕБУЮТСЯ ИСПРАВЛЕНИЯ") . "\n\n";

    if (!$status['success']) {
        echo "Необходимо исправить:\n";
        foreach ($status['checks'] as $name => $check) {
            if ($check['status'] === 'ERROR') {
                echo "- {$name}: " . ($check['details'] ?? 'Проверка не пройдена') . "\n";
            }
        }
        exit(1);
    }

} catch (Exception $e) {
    echo "\nОшибка при проверке: " . $e->getMessage() . "\n";
    Logger::error('Deploy check failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
} 
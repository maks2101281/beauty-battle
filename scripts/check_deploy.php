<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../classes/Logger.php';

echo "Проверка развертывания Beauty Battle\n";
echo "===================================\n\n";

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
    // 1. Проверка переменных окружения
    echo "1. Проверка переменных окружения:\n";
    $required_vars = [
        'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD',
        'APP_URL', 'APP_ENV'
    ];
    
    foreach ($required_vars as $var) {
        $value = Env::get($var);
        echo "   {$var}: " . ($value ? "OK" : "ОТСУТСТВУЕТ") . "\n";
        addCheck("env_{$var}", !empty($value));
    }
    echo "\n";
    
    // 2. Проверка директорий
    echo "2. Проверка директорий:\n";
    $directories = [
        'public/uploads/photos',
        'public/uploads/videos',
        'public/uploads/thumbnails',
        'cache',
        'logs'
    ];
    
    foreach ($directories as $dir) {
        $fullPath = __DIR__ . '/../' . $dir;
        $exists = file_exists($fullPath);
        $writable = is_writable($fullPath);
        echo "   {$dir}: " . 
             ($exists ? "существует" : "отсутствует") . ", " .
             ($writable ? "доступна для записи" : "нет прав на запись") . "\n";
        addCheck("dir_{$dir}", $exists && $writable);
    }
    echo "\n";
    
    // 3. Проверка базы данных
    echo "3. Проверка базы данных:\n";
    try {
        require_once __DIR__ . '/../config/database_render.php';
        $pdo->query('SELECT 1');
        echo "   Подключение: OK\n";
        addCheck("database_connection", true);
        
        // Проверка таблиц
        $required_tables = [
            'contestants',
            'submissions',
            'auth_tokens',
            'voting_settings'
        ];
        
        foreach ($required_tables as $table) {
            $exists = $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
            echo "   Таблица {$table}: " . ($exists ? "OK" : "ОТСУТСТВУЕТ") . "\n";
            addCheck("table_{$table}", (bool)$exists);
        }
    } catch (PDOException $e) {
        echo "   ОШИБКА: " . $e->getMessage() . "\n";
        addCheck("database_connection", false, $e->getMessage());
    }
    echo "\n";
    
    // 4. Проверка PHP
    echo "4. Проверка PHP:\n";
    $required_extensions = ['pdo', 'pdo_pgsql', 'gd'];
    foreach ($required_extensions as $ext) {
        $loaded = extension_loaded($ext);
        echo "   Расширение {$ext}: " . ($loaded ? "OK" : "ОТСУТСТВУЕТ") . "\n";
        addCheck("ext_{$ext}", $loaded);
    }
    echo "\n";
    
    // 5. Проверка прав доступа
    echo "5. Проверка прав доступа:\n";
    $webuser = posix_getpwuid(posix_geteuid())['name'];
    echo "   Текущий пользователь: {$webuser}\n";
    addCheck("webuser", $webuser === 'www-data');
    
    // Проверяем права на запись в критичные директории
    foreach (['public/uploads', 'cache', 'logs'] as $dir) {
        $fullPath = __DIR__ . '/../' . $dir;
        $perms = substr(sprintf('%o', fileperms($fullPath)), -4);
        echo "   Права на {$dir}: {$perms}\n";
        addCheck("perms_{$dir}", $perms >= '0775');
    }
    
    // Выводим итоговый статус
    echo "\nИтоговый статус: " . ($status['success'] ? "OK" : "ЕСТЬ ПРОБЛЕМЫ") . "\n";
    if (!$status['success']) {
        echo "\nОбнаружены проблемы:\n";
        foreach ($status['checks'] as $name => $check) {
            if ($check['status'] === 'ERROR') {
                echo "- {$name}: " . ($check['details'] ?? 'Проверка не пройдена') . "\n";
            }
        }
        exit(1);
    }
    
} catch (Exception $e) {
    echo "\nКритическая ошибка: " . $e->getMessage() . "\n";
    Logger::error('Deployment check failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
} 
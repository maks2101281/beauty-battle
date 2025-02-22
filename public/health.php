<?php
header('Content-Type: application/json');

$status = [
    'status' => 'ok',
    'timestamp' => time(),
    'checks' => []
];

try {
    // Проверка версии PHP
    $required_php_version = '8.1.0';
    $status['checks']['php_version'] = [
        'status' => version_compare(PHP_VERSION, $required_php_version, '>=') ? 'ok' : 'error',
        'current' => PHP_VERSION,
        'required' => $required_php_version
    ];

    // Проверка базы данных
    require_once __DIR__ . '/../config/database_render.php';
    $pdo->query('SELECT 1');
    $status['checks']['database'] = [
        'status' => 'ok',
        'latency_ms' => null
    ];
    
    // Проверка директорий
    $directories = [
        'uploads' => __DIR__ . '/uploads',
        'cache' => __DIR__ . '/../cache',
        'logs' => __DIR__ . '/../logs'
    ];
    
    foreach ($directories as $name => $path) {
        $status['checks']['directory_' . $name] = [
            'status' => file_exists($path) && is_writable($path) ? 'ok' : 'error',
            'path' => $path,
            'writable' => is_writable($path)
        ];
    }
    
    // Проверка SSL сертификата
    $certFile = '/var/www/.postgresql/root.crt';
    $status['checks']['ssl_certificate'] = [
        'status' => file_exists($certFile) && is_readable($certFile) ? 'ok' : 'error',
        'path' => $certFile
    ];
    
    // Проверка PHP расширений
    $required_extensions = ['pdo', 'pdo_pgsql', 'gd'];
    foreach ($required_extensions as $ext) {
        $status['checks']['extension_' . $ext] = [
            'status' => extension_loaded($ext) ? 'ok' : 'error'
        ];
    }
    
    // Проверка настроек PHP
    $required_settings = [
        'upload_max_filesize' => '10M',
        'post_max_size' => '10M',
        'memory_limit' => '128M'
    ];
    
    foreach ($required_settings as $key => $expected) {
        $actual = ini_get($key);
        $status['checks']['php_setting_' . $key] = [
            'status' => $actual === $expected ? 'ok' : 'warning',
            'actual' => $actual,
            'expected' => $expected
        ];
    }
    
    // Проверка Apache модулей
    $required_modules = ['rewrite', 'headers'];
    foreach ($required_modules as $module) {
        $status['checks']['apache_module_' . $module] = [
            'status' => apache_module_loaded($module) ? 'ok' : 'error'
        ];
    }
    
    // Проверка свободного места
    $disk_free = disk_free_space('/');
    $disk_total = disk_total_space('/');
    $status['checks']['disk_space'] = [
        'status' => ($disk_free / $disk_total) > 0.1 ? 'ok' : 'warning',
        'free_bytes' => $disk_free,
        'total_bytes' => $disk_total,
        'free_percent' => round(($disk_free / $disk_total) * 100, 2)
    ];
    
    // Общий статус
    $has_errors = false;
    foreach ($status['checks'] as $check) {
        if ($check['status'] === 'error') {
            $has_errors = true;
            break;
        }
    }
    
    if ($has_errors) {
        $status['status'] = 'error';
        http_response_code(500);
    }
    
} catch (Exception $e) {
    $status['status'] = 'error';
    $status['error'] = $e->getMessage();
    http_response_code(500);
}

echo json_encode($status, JSON_PRETTY_PRINT);

// Вспомогательная функция для проверки модулей Apache
function apache_module_loaded($module) {
    if (!function_exists('apache_get_modules')) {
        return true; // Пропускаем проверку, если функция недоступна
    }
    return in_array($module, apache_get_modules());
} 
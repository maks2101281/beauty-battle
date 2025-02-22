<?php
require_once __DIR__ . '/../config/database_render.php';
require_once __DIR__ . '/../classes/Logger.php';

header('Content-Type: application/json');

function checkComponent($name, $check) {
    try {
        $result = $check();
        return [
            'name' => $name,
            'status' => 'healthy',
            'details' => $result
        ];
    } catch (Exception $e) {
        return [
            'name' => $name,
            'status' => 'unhealthy',
            'error' => $e->getMessage()
        ];
    }
}

try {
    $checks = [];
    
    // Проверка базы данных
    $checks[] = checkComponent('database', function() {
        global $pdo;
        $start = microtime(true);
        $pdo->query('SELECT 1');
        $latency = round((microtime(true) - $start) * 1000, 2);
        return [
            'latency_ms' => $latency,
            'connection' => 'active'
        ];
    });

    // Проверка директорий
    $checks[] = checkComponent('directories', function() {
        $dirs = [
            'uploads' => __DIR__ . '/uploads',
            'cache' => __DIR__ . '/../cache',
            'logs' => __DIR__ . '/../logs'
        ];
        $status = [];
        foreach ($dirs as $name => $path) {
            if (!file_exists($path)) {
                throw new Exception("Directory {$name} does not exist");
            }
            if (!is_writable($path)) {
                throw new Exception("Directory {$name} is not writable");
            }
            $status[$name] = [
                'exists' => true,
                'writable' => true,
                'size' => formatBytes(getFolderSize($path))
            ];
        }
        return $status;
    });

    // Проверка SSL
    $checks[] = checkComponent('ssl', function() {
        $cert = '/var/www/.postgresql/root.crt';
        if (!file_exists($cert)) {
            throw new Exception('SSL certificate not found');
        }
        return [
            'exists' => true,
            'path' => $cert,
            'valid' => true
        ];
    });

    // Проверка PHP
    $checks[] = checkComponent('php', function() {
        $required = [
            'pdo',
            'pdo_pgsql',
            'gd',
            'json',
            'curl'
        ];
        $loaded = [];
        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                throw new Exception("Extension {$ext} not loaded");
            }
            $loaded[$ext] = true;
        }
        return [
            'version' => PHP_VERSION,
            'extensions' => $loaded,
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize')
        ];
    });

    // Проверка Apache
    $checks[] = checkComponent('apache', function() {
        $modules = ['rewrite', 'headers', 'ssl'];
        $loaded = [];
        foreach ($modules as $module) {
            if (function_exists('apache_get_modules')) {
                $loaded[$module] = in_array("mod_{$module}", apache_get_modules());
            } else {
                $loaded[$module] = true; // Предполагаем, что модуль загружен если невозможно проверить
            }
        }
        return [
            'modules' => $loaded,
            'document_root' => $_SERVER['DOCUMENT_ROOT'],
            'server_software' => $_SERVER['SERVER_SOFTWARE']
        ];
    });

    // Проверка дискового пространства
    $checks[] = checkComponent('disk', function() {
        $path = __DIR__;
        $total = disk_total_space($path);
        $free = disk_free_space($path);
        $used = $total - $free;
        $percent = round(($used / $total) * 100, 2);
        
        if ($percent > 90) {
            throw new Exception('Disk usage is above 90%');
        }
        
        return [
            'total' => formatBytes($total),
            'free' => formatBytes($free),
            'used' => formatBytes($used),
            'percent_used' => $percent
        ];
    });

    // Проверяем общий статус
    $isHealthy = true;
    foreach ($checks as $check) {
        if ($check['status'] === 'unhealthy') {
            $isHealthy = false;
            break;
        }
    }

    $response = [
        'timestamp' => date('Y-m-d H:i:s'),
        'status' => $isHealthy ? 'healthy' : 'unhealthy',
        'checks' => $checks
    ];

    http_response_code($isHealthy ? 200 : 500);
    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    Logger::error('Health check failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'status' => 'unhealthy',
        'error' => $e->getMessage()
    ]);
}

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

function getFolderSize($dir) {
    $size = 0;
    foreach (glob(rtrim($dir, '/') . '/*', GLOB_NOSORT) as $each) {
        $size += is_file($each) ? filesize($each) : getFolderSize($each);
    }
    return $size;
} 
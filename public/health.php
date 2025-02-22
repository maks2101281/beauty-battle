<?php
require_once __DIR__ . '/../config/database_render.php';
require_once __DIR__ . '/../classes/ErrorHandler.php';
require_once __DIR__ . '/../classes/Logger.php';

header('Content-Type: application/json');

try {
    // Инициализируем обработчик ошибок
    $errorHandler = ErrorHandler::getInstance();
    
    // Проверяем здоровье приложения
    $health = $errorHandler->checkHealth();
    
    // Добавляем дополнительные проверки
    $health['checks'] = [
        'database' => [
            'status' => 'healthy',
            'latency' => checkDatabaseLatency()
        ],
        'disk' => checkDiskSpace(),
        'memory' => checkMemoryUsage(),
        'cache' => checkCache(),
        'uploads' => checkUploads(),
        'logs' => checkLogs()
    ];
    
    // Проверяем общий статус
    $health['status'] = in_array('unhealthy', array_column($health['checks'], 'status')) ? 'unhealthy' : 'healthy';
    
    // Устанавливаем код ответа
    http_response_code($health['status'] === 'healthy' ? 200 : 500);
    
    // Логируем результат проверки
    Logger::info('Health check completed', $health);
    
    echo json_encode($health, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    Logger::error('Health check failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'status' => 'unhealthy',
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Проверка задержки базы данных
 */
function checkDatabaseLatency() {
    $start = microtime(true);
    Database::getInstance()->query('SELECT 1');
    return round((microtime(true) - $start) * 1000, 2); // в миллисекундах
}

/**
 * Проверка дискового пространства
 */
function checkDiskSpace() {
    $uploadDir = __DIR__ . '/uploads';
    $total = disk_total_space($uploadDir);
    $free = disk_free_space($uploadDir);
    $used = $total - $free;
    $percentUsed = round(($used / $total) * 100, 2);
    
    return [
        'status' => $percentUsed < 90 ? 'healthy' : 'unhealthy',
        'total' => formatBytes($total),
        'free' => formatBytes($free),
        'used' => formatBytes($used),
        'percent_used' => $percentUsed
    ];
}

/**
 * Проверка использования памяти
 */
function checkMemoryUsage() {
    $memoryLimit = ini_get('memory_limit');
    $memoryUsage = memory_get_usage(true);
    $peakMemoryUsage = memory_get_peak_usage(true);
    
    return [
        'status' => 'healthy',
        'current' => formatBytes($memoryUsage),
        'peak' => formatBytes($peakMemoryUsage),
        'limit' => $memoryLimit
    ];
}

/**
 * Проверка кэша
 */
function checkCache() {
    $cacheDir = __DIR__ . '/../cache';
    
    if (!is_dir($cacheDir)) {
        return ['status' => 'unhealthy', 'error' => 'Cache directory does not exist'];
    }
    
    if (!is_writable($cacheDir)) {
        return ['status' => 'unhealthy', 'error' => 'Cache directory is not writable'];
    }
    
    // Пробуем записать тестовый файл
    $testFile = $cacheDir . '/test.tmp';
    if (file_put_contents($testFile, 'test') === false) {
        return ['status' => 'unhealthy', 'error' => 'Cannot write to cache directory'];
    }
    unlink($testFile);
    
    return [
        'status' => 'healthy',
        'size' => formatBytes(getFolderSize($cacheDir))
    ];
}

/**
 * Проверка загрузок
 */
function checkUploads() {
    $uploadDir = __DIR__ . '/uploads';
    $subdirs = ['photos', 'videos', 'thumbnails'];
    $status = [];
    
    foreach ($subdirs as $dir) {
        $path = $uploadDir . '/' . $dir;
        if (!is_dir($path)) {
            $status[$dir] = [
                'status' => 'unhealthy',
                'error' => 'Directory does not exist'
            ];
            continue;
        }
        
        if (!is_writable($path)) {
            $status[$dir] = [
                'status' => 'unhealthy',
                'error' => 'Directory is not writable'
            ];
            continue;
        }
        
        $status[$dir] = [
            'status' => 'healthy',
            'size' => formatBytes(getFolderSize($path)),
            'files' => count(glob($path . '/*'))
        ];
    }
    
    return [
        'status' => in_array('unhealthy', array_column($status, 'status')) ? 'unhealthy' : 'healthy',
        'directories' => $status
    ];
}

/**
 * Проверка логов
 */
function checkLogs() {
    $logDir = __DIR__ . '/../logs';
    
    if (!is_dir($logDir)) {
        return ['status' => 'unhealthy', 'error' => 'Log directory does not exist'];
    }
    
    if (!is_writable($logDir)) {
        return ['status' => 'unhealthy', 'error' => 'Log directory is not writable'];
    }
    
    $logFiles = glob($logDir . '/*.log');
    $status = [];
    
    foreach ($logFiles as $file) {
        $name = basename($file);
        $size = filesize($file);
        $status[$name] = [
            'size' => formatBytes($size),
            'modified' => date('Y-m-d H:i:s', filemtime($file))
        ];
    }
    
    return [
        'status' => 'healthy',
        'files' => $status
    ];
}

/**
 * Форматирование размера в байтах
 */
function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Получение размера директории
 */
function getFolderSize($dir) {
    $size = 0;
    foreach (glob(rtrim($dir, '/') . '/*', GLOB_NOSORT) as $each) {
        $size += is_file($each) ? filesize($each) : getFolderSize($each);
    }
    return $size;
} 
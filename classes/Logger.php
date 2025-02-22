<?php

class Logger {
    private const LOG_DIR = __DIR__ . '/../logs/';
    private const MAX_LOG_SIZE = 10485760; // 10MB
    private const MAX_LOG_FILES = 5;

    private static $logLevels = [
        'ERROR' => 0,
        'WARNING' => 1,
        'INFO' => 2,
        'DEBUG' => 3
    ];

    /**
     * Инициализация директории для логов
     */
    private static function init() {
        if (!file_exists(self::LOG_DIR)) {
            if (!mkdir(self::LOG_DIR, 0755, true)) {
                throw new Exception('Не удалось создать директорию для логов');
            }
        }
    }

    /**
     * Ротация логов при превышении размера
     */
    private static function rotateLog($logFile) {
        if (!file_exists($logFile) || filesize($logFile) < self::MAX_LOG_SIZE) {
            return;
        }

        for ($i = self::MAX_LOG_FILES - 1; $i > 0; $i--) {
            $oldFile = $logFile . '.' . $i;
            $newFile = $logFile . '.' . ($i + 1);
            if (file_exists($oldFile)) {
                rename($oldFile, $newFile);
            }
        }

        rename($logFile, $logFile . '.1');
    }

    /**
     * Запись лога
     */
    private static function log($level, $message, array $context = []) {
        self::init();

        $timestamp = date('Y-m-d H:i:s');
        $logFile = self::LOG_DIR . strtolower($level) . '.log';
        
        // Ротация логов
        self::rotateLog($logFile);

        // Форматируем контекст
        $contextStr = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        
        // Получаем информацию о вызове
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        $caller = isset($trace['class']) ? 
            $trace['class'] . $trace['type'] . $trace['function'] : 
            $trace['function'];
        
        // Форматируем сообщение
        $logMessage = sprintf(
            "[%s] %s [%s] %s%s%s",
            $timestamp,
            $level,
            $caller,
            $message,
            $contextStr,
            PHP_EOL
        );

        // Записываем лог
        if (file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX) === false) {
            throw new Exception('Не удалось записать лог');
        }

        // Устанавливаем права на файл
        chmod($logFile, 0644);
    }

    /**
     * Логирование ошибки
     */
    public static function error($message, array $context = []) {
        self::log('ERROR', $message, $context);
    }

    /**
     * Логирование предупреждения
     */
    public static function warning($message, array $context = []) {
        self::log('WARNING', $message, $context);
    }

    /**
     * Логирование информационного сообщения
     */
    public static function info($message, array $context = []) {
        self::log('INFO', $message, $context);
    }

    /**
     * Логирование отладочной информации
     */
    public static function debug($message, array $context = []) {
        self::log('DEBUG', $message, $context);
    }

    /**
     * Получение всех логов определенного уровня
     */
    public static function getLogs($level = null, $limit = 100) {
        self::init();
        
        $logs = [];
        $pattern = $level ? 
            self::LOG_DIR . strtolower($level) . '.log*' : 
            self::LOG_DIR . '*.log*';
        
        foreach (glob($pattern) as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }
            
            $lines = array_filter(explode(PHP_EOL, $content));
            $logs = array_merge($logs, array_slice($lines, -$limit));
        }
        
        return array_slice($logs, -$limit);
    }

    /**
     * Очистка старых логов
     */
    public static function cleanup($days = 30) {
        self::init();
        
        $files = glob(self::LOG_DIR . '*.log*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file) && $now - filemtime($file) >= 86400 * $days) {
                unlink($file);
            }
        }
    }
} 
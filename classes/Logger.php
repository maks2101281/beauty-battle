<?php
class Logger {
    private static $logDir;
    
    public static function init() {
        self::$logDir = __DIR__ . '/../logs/';
        if (!file_exists(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
    }
    
    public static function log($level, $message, $context = []) {
        if (!self::$logDir) {
            self::init();
        }
        
        $logFile = sprintf(
            '%s%s-%s.log',
            self::$logDir,
            date('Y-m-d'),
            $level
        );
        
        $logEntry = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            json_encode($context)
        );
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
    
    public static function error($message, $context = []) {
        self::log('error', $message, $context);
    }
    
    public static function info($message, $context = []) {
        self::log('info', $message, $context);
    }
    
    public static function debug($message, $context = []) {
        if (Env::get('APP_DEBUG', false)) {
            self::log('debug', $message, $context);
        }
    }
} 
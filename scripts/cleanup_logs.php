<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../classes/Logger.php';

class LogCleaner {
    private static $logDir;
    private static $retentionDays;
    
    public static function init() {
        self::$logDir = __DIR__ . '/../logs/';
        self::$retentionDays = Env::get('LOG_RETENTION_DAYS', 30);
    }
    
    public static function cleanup() {
        self::init();
        
        try {
            $files = glob(self::$logDir . '*.log');
            $now = time();
            $deletedCount = 0;
            
            foreach ($files as $file) {
                $fileTime = filemtime($file);
                $daysOld = floor(($now - $fileTime) / (60 * 60 * 24));
                
                if ($daysOld > self::$retentionDays) {
                    unlink($file);
                    $deletedCount++;
                }
            }
            
            Logger::info('Log cleanup completed', [
                'deleted_files' => $deletedCount,
                'retention_days' => self::$retentionDays
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Log cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}

// Запускаем очистку
if (php_sapi_name() === 'cli') {
    LogCleaner::cleanup();
} 
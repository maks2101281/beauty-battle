<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../classes/Logger.php';

class SessionCleaner {
    private static $redis;
    
    public static function init() {
        self::$redis = new Redis();
        self::$redis->connect(
            Env::get('REDIS_HOST'),
            Env::get('REDIS_PORT')
        );
        
        if ($password = Env::get('REDIS_PASSWORD')) {
            self::$redis->auth($password);
        }
    }
    
    public static function cleanup() {
        self::init();
        
        try {
            // Получаем все ключи сессий
            $sessionKeys = self::$redis->keys('session:*');
            $now = time();
            $lifetime = Env::get('SESSION_LIFETIME', 120) * 60; // в секундах
            $deletedCount = 0;
            
            foreach ($sessionKeys as $key) {
                $sessionData = self::$redis->get($key);
                if ($sessionData) {
                    $data = json_decode($sessionData, true);
                    if (isset($data['last_activity']) && ($now - $data['last_activity'] > $lifetime)) {
                        self::$redis->del($key);
                        $deletedCount++;
                    }
                }
            }
            
            Logger::info('Session cleanup completed', [
                'deleted_sessions' => $deletedCount,
                'lifetime_minutes' => $lifetime / 60
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Session cleanup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}

// Запускаем очистку
if (php_sapi_name() === 'cli') {
    SessionCleaner::cleanup();
} 
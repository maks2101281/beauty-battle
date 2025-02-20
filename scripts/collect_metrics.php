<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../classes/Monitor.php';
require_once __DIR__ . '/../classes/Logger.php';

class MetricsCollector {
    private static $pdo;
    
    public static function init() {
        self::$pdo = new PDO(
            "pgsql:host=" . Env::get('DB_HOST') . 
            ";dbname=" . Env::get('DB_NAME'),
            Env::get('DB_USER'),
            Env::get('DB_PASSWORD')
        );
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    public static function collect() {
        self::init();
        
        try {
            // Собираем различные метрики
            
            // 1. Общее количество голосов
            $stmt = self::$pdo->query("SELECT COUNT(*) FROM votes");
            Monitor::track('total_votes', $stmt->fetchColumn());
            
            // 2. Количество голосов за последний час
            $stmt = self::$pdo->query("
                SELECT COUNT(*) 
                FROM votes 
                WHERE created_at > NOW() - INTERVAL '1 hour'
            ");
            Monitor::track('hourly_votes', $stmt->fetchColumn());
            
            // 3. Активные пользователи
            $stmt = self::$pdo->query("
                SELECT COUNT(DISTINCT user_id) 
                FROM votes 
                WHERE created_at > NOW() - INTERVAL '24 hours'
            ");
            Monitor::track('daily_active_users', $stmt->fetchColumn());
            
            // 4. Количество комментариев
            $stmt = self::$pdo->query("SELECT COUNT(*) FROM comments");
            Monitor::track('total_comments', $stmt->fetchColumn());
            
            // 5. Средний рейтинг участников
            $stmt = self::$pdo->query("
                SELECT AVG(rating)::numeric(10,2) 
                FROM contestants
            ");
            Monitor::track('avg_rating', $stmt->fetchColumn());
            
            // 6. Использование памяти Redis
            $redis = new Redis();
            $redis->connect(Env::get('REDIS_HOST'), Env::get('REDIS_PORT'));
            $info = $redis->info();
            Monitor::track('redis_used_memory', $info['used_memory']);
            
            // 7. Размер базы данных
            $stmt = self::$pdo->query("
                SELECT pg_database_size(current_database()) / 1024 / 1024 as size_mb
            ");
            Monitor::track('db_size_mb', $stmt->fetchColumn());
            
            Logger::info('Metrics collection completed');
            return true;
            
        } catch (Exception $e) {
            Logger::error('Metrics collection failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}

// Запускаем сбор метрик
if (php_sapi_name() === 'cli') {
    MetricsCollector::collect();
} 
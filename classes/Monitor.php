<?php
class Monitor {
    private static $redis;
    
    public static function init() {
        if (!self::$redis) {
            self::$redis = new Redis();
            self::$redis->connect(Env::get('REDIS_HOST'), Env::get('REDIS_PORT'));
        }
    }
    
    public static function track($metric, $value = 1) {
        self::init();
        
        $key = "metric:{$metric}";
        if (is_numeric($value)) {
            self::$redis->incrBy($key, $value);
        } else {
            self::$redis->set($key, $value);
        }
        
        // Сохраняем историю метрик
        $historyKey = "metric_history:{$metric}:" . date('Y-m-d');
        self::$redis->rPush($historyKey, json_encode([
            'timestamp' => time(),
            'value' => $value
        ]));
        
        // Устанавливаем TTL для истории (30 дней)
        self::$redis->expire($historyKey, 2592000);
    }
    
    public static function get($metric) {
        self::init();
        return self::$redis->get("metric:{$metric}");
    }
    
    public static function getHistory($metric, $days = 7) {
        self::init();
        
        $history = [];
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $key = "metric_history:{$metric}:{$date}";
            $data = self::$redis->lRange($key, 0, -1);
            if ($data) {
                $history[$date] = array_map('json_decode', $data);
            }
        }
        
        return $history;
    }
} 
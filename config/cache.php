<?php
return [
    'driver' => 'redis',
    'host' => Env::get('REDIS_HOST', 'localhost'),
    'port' => Env::get('REDIS_PORT', 6379),
    'password' => Env::get('REDIS_PASSWORD', null),
    'ttl' => 3600, // 1 час по умолчанию
    
    // Настройки для различных типов кэша
    'ttl_map' => [
        'contestants' => 1800,    // 30 минут
        'comments' => 300,        // 5 минут
        'leaderboard' => 600,     // 10 минут
        'user_data' => 86400,     // 24 часа
        'static_data' => 604800,  // 7 дней
    ],
    
    // Префиксы для ключей
    'prefix' => [
        'default' => 'cache:',
        'session' => 'session:',
        'rate_limit' => 'rate_limit:',
        'metric' => 'metric:',
    ]
]; 
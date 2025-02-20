<?php
return [
    'telegram' => [
        'bot_token' => '7603535293:AAHCWb3_P9XKHMmaPOOp-dGAcZi35r4KHDs',
        'bot_username' => 'AUTH_CE_BOT',
    ],
    
    // Настройки безопасности
    'security' => [
        'max_attempts_per_hour' => 3, // Максимальное количество попыток отправки кода в час
        'code_lifetime' => 300,       // Время жизни кода в секундах (5 минут)
        'block_time' => 3600,         // Время блокировки после превышения лимита (1 час)
    ]
]; 
<?php

function cors() {
    // Разрешаем запросы с любого источника в режиме разработки
    if (Env::get('APP_ENV') === 'development') {
        header('Access-Control-Allow-Origin: *');
    } else {
        // В продакшене разрешаем только с нашего домена
        $allowedOrigins = [
            'https://beauty-battle-1.onrender.com',
            'http://localhost:8000'
        ];
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array($origin, $allowedOrigins)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
    
    // Preflight request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('HTTP/1.1 200 OK');
        exit();
    }
} 
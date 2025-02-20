<?php
require_once __DIR__ . '/../config/env.php';

// Отладочная информация
if (Env::get('APP_DEBUG', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    Env::dump();
}

require_once __DIR__ . '/../config/database_render.php';

// Маршрутизация
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// Обработка API запросов
if (strpos($path, '/api/') === 0) {
    $apiFile = __DIR__ . '/..' . $path;
    if (file_exists($apiFile)) {
        require_once $apiFile;
        exit;
    }
}

// Если это не API запрос, отдаем HTML файл
$htmlFile = __DIR__ . rtrim($path, '/') . '.html';
if (file_exists($htmlFile)) {
    readfile($htmlFile);
    exit;
}

// Если файл не найден, отдаем index.html
readfile(__DIR__ . '/index.html'); 
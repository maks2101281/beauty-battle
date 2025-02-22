<?php
// Включаем отображение ошибок в режиме разработки
if (getenv('APP_ENV') === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Получаем путь из URL
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path = ltrim($path, '/');

// Если путь пустой, показываем главную страницу
if ($path === '') {
    readfile(__DIR__ . '/index.html');
    exit;
}

// Обработка API запросов
if (strpos($path, 'api/') === 0) {
    $api_file = __DIR__ . '/../' . $path;
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    }
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'API endpoint not found']);
    exit;
}

// Проверяем существование файла
$file = __DIR__ . '/' . $path;

// Если это HTML файл
if (preg_match('/\.html$/', $path)) {
    if (file_exists($file)) {
        readfile($file);
        exit;
    }
}

// Если это статический файл (css, js, images)
if (preg_match('/\.(css|js|jpg|jpeg|png|gif|webp|ico)$/', $path)) {
    if (file_exists($file)) {
        // Определяем MIME тип
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $mime_types = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon'
        ];
        
        if (isset($mime_types[$extension])) {
            header('Content-Type: ' . $mime_types[$extension]);
        }
        
        readfile($file);
        exit;
    }
}

// Если файл не найден, показываем 404 страницу
if (file_exists(__DIR__ . '/errors/404.html')) {
    header('HTTP/1.1 404 Not Found');
    readfile(__DIR__ . '/errors/404.html');
} else {
    header('HTTP/1.1 404 Not Found');
    echo '404 Not Found';
} 
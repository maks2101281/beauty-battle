<?php
session_start();

// Включаем отображение ошибок для отладки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Логируем входящий запрос
error_log('Request Method: ' . $_SERVER['REQUEST_METHOD']);
error_log('Request Headers: ' . print_r(getallheaders(), true));

// Базовые заголовки для всех ответов
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/ImageProcessor.php';

try {
    // Логируем полученные данные
    error_log('POST data: ' . print_r($_POST, true));
    error_log('FILES data: ' . print_r($_FILES, true));

    // Проверяем метод запроса
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Метод не поддерживается');
    }

    // Проверяем наличие файла
    if (!isset($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Ошибка загрузки файла: ' . ($_FILES['media']['error'] ?? 'файл не найден'));
    }

    $type = $_POST['type'] ?? 'photo';
    $file = $_FILES['media'];
    $name = $_POST['name'] ?? '';
    $social = $_POST['social'] ?? '';

    // Проверяем тип файла
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // Создаем директории если не существуют
    $uploadDir = __DIR__ . '/../public/uploads/';
    $photoDir = $uploadDir . 'photos/';
    $videoDir = $uploadDir . 'videos/';
    $thumbDir = $uploadDir . 'thumbnails/';

    foreach ([$photoDir, $videoDir, $thumbDir] as $dir) {
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0777, true)) {
                throw new Exception('Не удалось создать директорию: ' . $dir);
            }
        }
    }

    // Генерируем уникальное имя файла
    $filename = uniqid() . '_' . time();

    if ($type === 'photo') {
        // Проверяем, что это изображение
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'])) {
            throw new Exception('Недопустимый формат изображения');
        }

        // Определяем расширение файла
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new Exception('Неподдерживаемый формат изображения')
        };

        $finalFilename = $filename . '.' . $extension;
        
        // Просто копируем файл для тестирования
        if (!copy($file['tmp_name'], $photoDir . $finalFilename)) {
            throw new Exception('Ошибка при сохранении изображения');
        }
        
        // Копируем тот же файл для миниатюры (в реальном приложении здесь будет создание миниатюры)
        if (!copy($file['tmp_name'], $thumbDir . $finalFilename)) {
            throw new Exception('Ошибка при создании миниатюры');
        }

        $mediaPath = 'uploads/photos/' . $finalFilename;
        $thumbPath = 'uploads/thumbnails/' . $finalFilename;
    } else {
        // Проверяем, что это видео
        if (!in_array($mimeType, ['video/mp4', 'video/webm'])) {
            throw new Exception('Недопустимый формат видео');
        }

        // Проверяем размер видео
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception('Видео слишком большое (максимум 10MB)');
        }

        // Сохраняем видео
        $extension = $mimeType === 'video/mp4' ? 'mp4' : 'webm';
        $finalFilename = $filename . '.' . $extension;
        
        if (!move_uploaded_file($file['tmp_name'], $videoDir . $finalFilename)) {
            throw new Exception('Ошибка при сохранении видео');
        }

        $mediaPath = 'uploads/videos/' . $finalFilename;
        $thumbPath = null;
    }

    // Сохраняем в базу данных
    $stmt = $pdo->prepare("
        INSERT INTO submissions (
            name, media_type, media_path, thumbnail_path,
            social_link, user_id, status
        ) VALUES (
            ?, ?, ?, ?, ?, ?, 'pending'
        )
    ");

    if (!$stmt->execute([
        $name,
        $type,
        $mediaPath,
        $thumbPath,
        $social,
        1 // Временный user_id для тестирования
    ])) {
        throw new Exception('Ошибка при сохранении в базу данных');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Файл успешно загружен',
        'media' => $mediaPath
    ]);

} catch (Exception $e) {
    error_log('Submit Error: ' . $e->getMessage());
    error_log('Debug backtrace: ' . print_r(debug_backtrace(), true));
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 
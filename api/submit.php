<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../classes/ImageProcessor.php';

// Включаем CORS
cors();

header('Content-Type: application/json');

try {
    // Проверяем CSRF-токен
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
            throw new Exception('Недействительный CSRF-токен');
        }
    }
    
    // Проверяем авторизацию
    session_start();
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Требуется авторизация');
    }
    
    // Проверяем наличие файла
    if (!isset($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Ошибка загрузки файла');
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
            mkdir($dir, 0777, true);
        }
    }

    // Генерируем уникальное имя файла
    $filename = uniqid() . '_' . time();

    if ($type === 'photo') {
        // Проверяем, что это изображение
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'])) {
            throw new Exception('Недопустимый формат изображения');
        }

        // Обрабатываем изображение
        $imageProcessor = new ImageProcessor();
        $extension = $imageProcessor->getExtensionFromMime($mimeType);
        $finalFilename = $filename . '.' . $extension;
        
        $imageProcessor->processImage(
            $file['tmp_name'],
            $photoDir . $finalFilename
        );
        
        $imageProcessor->generateThumbnail(
            $file['tmp_name'],
            $thumbDir . $finalFilename
        );

        $mediaPath = 'uploads/photos/' . $finalFilename;
        $thumbPath = 'uploads/thumbnails/' . $finalFilename;
    } else {
        // Проверяем, что это видео
        if (!in_array($mimeType, ['video/mp4', 'video/webm'])) {
            throw new Exception('Недопустимый формат видео');
        }

        // Проверяем размер видео
        if ($file['size'] > 10 * 1024 * 1024) { // 10MB
            throw new Exception('Видео слишком большое');
        }

        // Сохраняем видео
        $extension = $mimeType === 'video/mp4' ? 'mp4' : 'webm';
        $finalFilename = $filename . '.' . $extension;
        move_uploaded_file($file['tmp_name'], $videoDir . $finalFilename);

        $mediaPath = 'uploads/videos/' . $finalFilename;
        $thumbPath = null;
    }

    // Сохраняем в базу данных
    $stmt = $pdo->prepare("
        INSERT INTO submissions (
            name, media_type, media_path, thumbnail_path,
            social_link, user_id, status, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, 'pending', NOW()
        )
    ");

    $stmt->execute([
        $name,
        $type,
        $mediaPath,
        $thumbPath,
        $social,
        $_SESSION['user_id']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Файл успешно загружен',
        'media' => $mediaPath
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 
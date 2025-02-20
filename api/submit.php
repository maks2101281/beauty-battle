<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../classes/ImageProcessor.php';

header('Content-Type: application/json');

try {
    // Проверяем CSRF-токен
    if (!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        throw new Exception('Недействительный CSRF-токен');
    }
    
    // Проверяем авторизацию
    session_start();
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Требуется авторизация');
    }
    
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Ошибка загрузки файла');
    }
    
    // Создаем экземпляр обработчика изображений
    $imageProcessor = new ImageProcessor();
    
    // Валидируем изображение
    $imageProcessor->validateImage($_FILES['photo']);
    
    // Генерируем уникальное имя файла
    $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $uploadPath = __DIR__ . '/../uploads/photos/' . $filename;
    $thumbPath = __DIR__ . '/../uploads/thumbnails/' . $filename;
    
    // Создаем директории, если не существуют
    if (!file_exists(__DIR__ . '/../uploads/photos/')) {
        mkdir(__DIR__ . '/../uploads/photos/', 0777, true);
    }
    if (!file_exists(__DIR__ . '/../uploads/thumbnails/')) {
        mkdir(__DIR__ . '/../uploads/thumbnails/', 0777, true);
    }
    
    // Обрабатываем и сохраняем изображение
    $imageProcessor->processImage($_FILES['photo']['tmp_name'], $uploadPath);
    
    // Создаем миниатюру
    $imageProcessor->generateThumbnail($_FILES['photo']['tmp_name'], $thumbPath);
    
    // Сохраняем информацию в базу данных
    $stmt = $pdo->prepare("
        INSERT INTO contestants (
            name, photo, thumbnail, user_id, created_at
        ) VALUES (
            ?, ?, ?, ?, NOW()
        )
    ");
    
    $stmt->execute([
        $_POST['name'],
        '/uploads/photos/' . $filename,
        '/uploads/thumbnails/' . $filename,
        $_SESSION['user_id']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Фотография успешно загружена',
        'photo' => '/uploads/photos/' . $filename
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 
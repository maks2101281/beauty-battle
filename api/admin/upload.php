<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database_render.php';
require_once __DIR__ . '/../../api/middleware/AdminMiddleware.php';

try {
    // Проверяем права администратора
    AdminMiddleware::checkAccess();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Метод не поддерживается');
    }

    // Проверяем наличие файла и данных
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Ошибка загрузки файла');
    }

    if (!isset($_POST['name']) || empty($_POST['name'])) {
        throw new Exception('Имя участницы обязательно');
    }

    $file = $_FILES['photo'];
    $name = $_POST['name'];

    // Проверяем тип файла
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('Недопустимый тип файла. Разрешены только: JPEG, PNG, WebP');
    }

    // Проверяем размер файла (максимум 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('Файл слишком большой. Максимальный размер: 5MB');
    }

    // Генерируем уникальное имя файла
    $extension = match ($mime_type) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    };
    
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $upload_path = __DIR__ . '/../../public/uploads/photos/' . $filename;

    // Создаем директорию если не существует
    $upload_dir = dirname($upload_path);
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Перемещаем файл
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception('Ошибка при сохранении файла');
    }

    // Сохраняем в базу данных
    $photo_url = '/uploads/photos/' . $filename;
    
    $stmt = $pdo->prepare("
        INSERT INTO contestants (name, photo, created_at)
        VALUES (?, ?, CURRENT_TIMESTAMP)
        RETURNING id
    ");
    
    $stmt->execute([$name, $photo_url]);
    $contestant_id = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'message' => 'Участница успешно добавлена',
        'contestant' => [
            'id' => $contestant_id,
            'name' => $name,
            'photo' => $photo_url
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 
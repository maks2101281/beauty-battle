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

    // Получаем данные
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        throw new Exception('ID участницы не указан');
    }

    // Получаем информацию о фото для удаления
    $stmt = $pdo->prepare("
        SELECT photo FROM contestants WHERE id = ?
    ");
    $stmt->execute([$data['id']]);
    $contestant = $stmt->fetch();

    if (!$contestant) {
        throw new Exception('Участница не найдена');
    }

    // Начинаем транзакцию
    $pdo->beginTransaction();

    try {
        // Удаляем из базы данных
        $stmt = $pdo->prepare("
            DELETE FROM contestants WHERE id = ?
        ");
        $stmt->execute([$data['id']]);

        // Удаляем файл фото
        $photo_path = __DIR__ . '/../../public' . $contestant['photo'];
        if (file_exists($photo_path)) {
            unlink($photo_path);
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Участница успешно удалена'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 
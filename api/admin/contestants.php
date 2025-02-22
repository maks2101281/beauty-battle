<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database_render.php';
require_once __DIR__ . '/../../api/middleware/AdminMiddleware.php';

try {
    // Проверяем права администратора
    AdminMiddleware::checkAccess();

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Метод не поддерживается');
    }

    // Получаем список всех участниц
    $stmt = $pdo->query("
        SELECT id, name, photo, created_at
        FROM contestants
        ORDER BY created_at DESC
    ");
    
    $contestants = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'contestants' => $contestants
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 
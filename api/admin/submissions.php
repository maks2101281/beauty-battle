<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/csrf.php';

header('Content-Type: application/json');

// Проверяем авторизацию администратора
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Доступ запрещен']);
    exit;
}

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Получаем список предложенных участниц
            $stmt = $pdo->prepare("
                SELECT 
                    s.*,
                    u.username as submitted_by
                FROM submissions s
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.status = ?
                ORDER BY s.created_at DESC
            ");
            
            $status = $_GET['status'] ?? 'pending';
            $stmt->execute([$status]);
            $submissions = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'submissions' => $submissions
            ]);
            break;

        case 'POST':
            // Проверяем CSRF-токен
            if (!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
                throw new Exception('Недействительный CSRF-токен');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $action = $data['action'] ?? '';
            $submissionId = $data['submission_id'] ?? null;

            if (!$submissionId) {
                throw new Exception('ID предложения не указан');
            }

            switch ($action) {
                case 'approve':
                    // Получаем данные предложения
                    $stmt = $pdo->prepare("
                        SELECT * FROM submissions WHERE id = ?
                    ");
                    $stmt->execute([$submissionId]);
                    $submission = $stmt->fetch();

                    if (!$submission) {
                        throw new Exception('Предложение не найдено');
                    }

                    // Добавляем в contestants
                    $stmt = $pdo->prepare("
                        INSERT INTO contestants (
                            name, media_type, media_path, thumbnail_path,
                            social_link, created_at
                        ) VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $submission['name'],
                        $submission['media_type'],
                        $submission['media_path'],
                        $submission['thumbnail_path'],
                        $submission['social_link']
                    ]);

                    // Обновляем статус предложения
                    $stmt = $pdo->prepare("
                        UPDATE submissions 
                        SET status = 'approved', 
                            approved_at = NOW(),
                            approved_by = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$_SESSION['user_id'], $submissionId]);

                    echo json_encode([
                        'success' => true,
                        'message' => 'Участница успешно добавлена'
                    ]);
                    break;

                case 'reject':
                    // Обновляем статус предложения
                    $stmt = $pdo->prepare("
                        UPDATE submissions 
                        SET status = 'rejected',
                            rejected_at = NOW(),
                            rejected_by = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$_SESSION['user_id'], $submissionId]);

                    echo json_encode([
                        'success' => true,
                        'message' => 'Предложение отклонено'
                    ]);
                    break;

                default:
                    throw new Exception('Неизвестное действие');
            }
            break;

        default:
            throw new Exception('Метод не поддерживается');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 
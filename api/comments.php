<?php
header('Content-Type: application/json');
require_once 'config/database.php';
require_once 'classes/AuthService.php';

// Проверка авторизации
function checkAuth() {
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
    
    if (!$token) {
        return false;
    }
    
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT telegram_username 
        FROM auth_tokens 
        WHERE token = ? AND expires_at > NOW() AND is_active = 1
    ");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Получение комментариев для участника
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $contestantId = $_GET['contestant_id'] ?? null;
        if (!$contestantId) {
            throw new Exception('Contestant ID is required');
        }
        
        $page = max(1, intval($_GET['page'] ?? 1));
        $perPage = 10;
        $offset = ($page - 1) * $perPage;
        
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.comment,
                c.likes,
                c.created_at,
                tu.username as author,
                (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id) as like_count
            FROM comments c
            JOIN telegram_users tu ON c.user_id = tu.id
            WHERE c.contestant_id = ? AND c.is_approved = 1
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$contestantId, $perPage, $offset]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Получаем общее количество комментариев
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM comments 
            WHERE contestant_id = ? AND is_approved = 1
        ");
        $stmt->execute([$contestantId]);
        $total = $stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'comments' => $comments,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total / $perPage),
                'total_comments' => $total
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// Добавление нового комментария
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $auth = checkAuth();
        if (!$auth) {
            throw new Exception('Unauthorized');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $contestantId = $data['contestant_id'] ?? null;
        $comment = trim($data['comment'] ?? '');
        
        if (!$contestantId || !$comment) {
            throw new Exception('Contestant ID and comment are required');
        }
        
        if (strlen($comment) > 1000) {
            throw new Exception('Comment is too long (max 1000 characters)');
        }
        
        // Получаем ID пользователя
        $stmt = $pdo->prepare("SELECT id FROM telegram_users WHERE username = ?");
        $stmt->execute([$auth['telegram_username']]);
        $userId = $stmt->fetchColumn();
        
        // Добавляем комментарий
        $stmt = $pdo->prepare("
            INSERT INTO comments (contestant_id, user_id, comment, is_approved)
            VALUES (?, ?, ?, TRUE)
        ");
        $stmt->execute([$contestantId, $userId, $comment]);
        
        $commentId = $pdo->lastInsertId();
        
        // Получаем добавленный комментарий
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.comment,
                c.likes,
                c.created_at,
                tu.username as author
            FROM comments c
            JOIN telegram_users tu ON c.user_id = tu.id
            WHERE c.id = ?
        ");
        $stmt->execute([$commentId]);
        $newComment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'comment' => $newComment
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

// Лайк комментария
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'like') {
    try {
        $auth = checkAuth();
        if (!$auth) {
            throw new Exception('Unauthorized');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $commentId = $data['comment_id'] ?? null;
        
        if (!$commentId) {
            throw new Exception('Comment ID is required');
        }
        
        // Получаем ID пользователя
        $stmt = $pdo->prepare("SELECT id FROM telegram_users WHERE username = ?");
        $stmt->execute([$auth['telegram_username']]);
        $userId = $stmt->fetchColumn();
        
        // Проверяем, не лайкнул ли уже пользователь этот комментарий
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM comment_likes 
            WHERE comment_id = ? AND user_id = ?
        ");
        $stmt->execute([$commentId, $userId]);
        
        if ($stmt->fetchColumn() > 0) {
            // Убираем лайк
            $stmt = $pdo->prepare("
                DELETE FROM comment_likes 
                WHERE comment_id = ? AND user_id = ?
            ");
            $stmt->execute([$commentId, $userId]);
            
            $stmt = $pdo->prepare("
                UPDATE comments 
                SET likes = likes - 1 
                WHERE id = ?
            ");
            $stmt->execute([$commentId]);
            
            $action = 'unliked';
        } else {
            // Добавляем лайк
            $stmt = $pdo->prepare("
                INSERT INTO comment_likes (comment_id, user_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$commentId, $userId]);
            
            $stmt = $pdo->prepare("
                UPDATE comments 
                SET likes = likes + 1 
                WHERE id = ?
            ");
            $stmt->execute([$commentId]);
            
            $action = 'liked';
        }
        
        // Получаем обновленное количество лайков
        $stmt = $pdo->prepare("
            SELECT likes 
            FROM comments 
            WHERE id = ?
        ");
        $stmt->execute([$commentId]);
        $likes = $stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'action' => $action,
            'likes' => $likes
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} 
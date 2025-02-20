<?php
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/database_render.php';

class AdminMiddleware {
    public static function checkAccess() {
        // Проверяем наличие токена
        $headers = getallheaders();
        $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
        
        if (!$token) {
            header('Location: /auth.html');
            exit;
        }
        
        try {
            global $pdo;
            
            // Проверяем токен и права администратора
            $stmt = $pdo->prepare("
                SELECT tu.* 
                FROM telegram_users tu
                JOIN auth_tokens at ON tu.username = at.telegram_username
                WHERE at.token = ? 
                AND at.expires_at > NOW() 
                AND at.is_active = true
                AND tu.is_admin = true
            ");
            
            $stmt->execute([$token]);
            $admin = $stmt->fetch();
            
            if (!$admin) {
                header('HTTP/1.1 403 Forbidden');
                echo json_encode([
                    'success' => false,
                    'error' => 'Доступ запрещен'
                ]);
                exit;
            }
            
            return $admin;
            
        } catch (PDOException $e) {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode([
                'success' => false,
                'error' => 'Ошибка сервера'
            ]);
            exit;
        }
    }
} 
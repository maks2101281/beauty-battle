<?php
class SecurityMiddleware {
    public function handle($request) {
        // Устанавливаем заголовки безопасности
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");
        header("X-Content-Type-Options: nosniff");
        header("Content-Security-Policy: default-src 'self'");
        
        // Проверяем CSRF токен
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
                throw new SecurityException('Invalid CSRF token');
            }
        }
        
        // Проверяем rate limiting
        $rateLimiter = new RateLimiter();
        if (!$rateLimiter->check($_SERVER['REMOTE_ADDR'])) {
            throw new SecurityException('Too many requests');
        }
    }
}

class SecurityException extends Exception {} 
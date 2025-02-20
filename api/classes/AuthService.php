<?php

class AuthService {
    private $config;
    private $pdo;
    private $telegram_token;
    
    public function __construct(PDO $pdo) {
        $this->config = require __DIR__ . '/../../config/auth.php';
        $this->pdo = $pdo;
        $this->telegram_token = getenv('TELEGRAM_BOT_TOKEN');
    }
    
    /**
     * Отправка кода через Telegram
     */
    public function sendVerificationCode($telegram_username) {
        // Проверяем ограничения
        if (!$this->checkLimits($telegram_username)) {
            throw new Exception("Превышен лимит попыток. Попробуйте позже.");
        }
        
        // Генерируем код
        $code = $this->generateCode();
        
        // Сохраняем код в базу
        $this->saveCode($telegram_username, $code);
        
        // Отправляем сообщение в Telegram
        $message = "Ваш код подтверждения для Beauty Battle: {$code}";
        return $this->sendTelegramMessage($telegram_username, $message);
    }
    
    /**
     * Отправка сообщения через Telegram Bot API
     */
    private function sendTelegramMessage($username, $message) {
        $url = "https://api.telegram.org/bot{$this->telegram_token}/sendMessage";
        
        $data = [
            'chat_id' => $username,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];
        
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception("Ошибка отправки сообщения в Telegram");
        }
        
        return true;
    }
    
    /**
     * Проверка кода подтверждения
     */
    public function verifyCode($telegram_username, $code) {
        $stmt = $this->pdo->prepare("
            SELECT code, created_at 
            FROM verification_codes 
            WHERE telegram_username = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$telegram_username]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$record) {
            return false;
        }
        
        // Проверяем время жизни кода
        $codeAge = time() - strtotime($record['created_at']);
        if ($codeAge > $this->config['security']['code_lifetime']) {
            return false;
        }
        
        return $record['code'] === $code;
    }
    
    /**
     * Проверка ограничений
     */
    private function checkLimits($telegram_username) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM verification_codes 
            WHERE telegram_username = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$telegram_username]);
        $hourlyAttempts = $stmt->fetchColumn();
        
        return $hourlyAttempts < $this->config['security']['max_attempts_per_hour'];
    }
    
    /**
     * Генерация кода
     */
    private function generateCode() {
        return str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Сохранение кода
     */
    private function saveCode($telegram_username, $code) {
        $stmt = $this->pdo->prepare("
            INSERT INTO verification_codes (telegram_username, code, created_at) 
            VALUES (?, ?, NOW())
        ");
        return $stmt->execute([$telegram_username, $code]);
    }
} 
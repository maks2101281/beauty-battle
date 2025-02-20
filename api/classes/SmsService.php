<?php

class SmsService {
    private $config;
    private $pdo;
    
    public function __construct(PDO $pdo) {
        $this->config = require __DIR__ . '/../../config/sms.php';
        $this->pdo = $pdo;
    }
    
    /**
     * Отправка SMS через SMSC.ru
     */
    public function sendSms($phone, $message) {
        $url = 'https://smsc.ru/sys/send.php?' . http_build_query([
            'login'    => $this->config['smsc']['login'],
            'psw'      => $this->config['smsc']['password'],
            'phones'   => $phone,
            'mes'      => $message,
            'sender'   => $this->config['smsc']['sender'],
            'charset'  => $this->config['smsc']['charset'],
            'fmt'      => 3, // Формат ответа в JSON
        ]);
        
        $response = file_get_contents($url);
        $result = json_decode($response, true);
        
        if (isset($result['error'])) {
            throw new Exception("SMS sending error: " . $result['error']);
        }
        
        return true;
    }
    
    /**
     * Генерация и отправка кода подтверждения
     */
    public function sendVerificationCode($phone) {
        // Проверяем ограничения
        if (!$this->checkLimits($phone)) {
            throw new Exception("Превышен лимит попыток отправки кода. Попробуйте позже.");
        }
        
        // Генерируем код
        $code = $this->generateCode();
        
        // Сохраняем код в базу
        $this->saveCode($phone, $code);
        
        // Отправляем SMS
        $message = "Ваш код подтверждения: {$code}";
        return $this->sendSms($phone, $message);
    }
    
    /**
     * Проверка кода подтверждения
     */
    public function verifyCode($phone, $code) {
        $stmt = $this->pdo->prepare("
            SELECT code, created_at 
            FROM verification_codes 
            WHERE phone = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$phone]);
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
     * Проверка ограничений на отправку кодов
     */
    private function checkLimits($phone) {
        // Проверяем количество попыток за час
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM verification_codes 
            WHERE phone = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$phone]);
        $hourlyAttempts = $stmt->fetchColumn();
        
        if ($hourlyAttempts >= $this->config['security']['max_attempts_per_hour']) {
            return false;
        }
        
        // Проверяем количество попыток за день
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM verification_codes 
            WHERE phone = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
        ");
        $stmt->execute([$phone]);
        $dailyAttempts = $stmt->fetchColumn();
        
        return $dailyAttempts < $this->config['security']['max_attempts_per_day'];
    }
    
    /**
     * Генерация кода подтверждения
     */
    private function generateCode() {
        return str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Сохранение кода в базу данных
     */
    private function saveCode($phone, $code) {
        $stmt = $this->pdo->prepare("
            INSERT INTO verification_codes (phone, code, created_at) 
            VALUES (?, ?, NOW())
        ");
        return $stmt->execute([$phone, $code]);
    }
} 
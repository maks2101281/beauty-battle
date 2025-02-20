<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/SmsService.php';
require_once __DIR__ . '/../config/database.php';

try {
    // Получаем данные запроса
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['phone']) || !isset($data['code'])) {
        throw new Exception('Phone number and code are required');
    }
    
    // Нормализация данных
    $phone = preg_replace('/[^0-9]/', '', $data['phone']);
    $code = preg_replace('/[^0-9]/', '', $data['code']);
    
    if (strlen($phone) !== 11) {
        throw new Exception('Invalid phone number format');
    }
    
    if (strlen($code) !== 4) {
        throw new Exception('Invalid code format');
    }
    
    // Инициализация сервиса
    $smsService = new SmsService($pdo);
    
    // Проверка кода
    if ($smsService->verifyCode($phone, $code)) {
        // Генерируем токен
        $token = bin2hex(random_bytes(32));
        
        // Сохраняем токен в базу
        $stmt = $pdo->prepare("
            INSERT INTO auth_tokens (phone, token, created_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$phone, $token]);
        
        echo json_encode([
            'success' => true,
            'token' => $token
        ]);
    } else {
        throw new Exception('Invalid verification code');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 
<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/SmsService.php';
require_once __DIR__ . '/../config/database.php';

try {
    // Получаем данные запроса
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['phone'])) {
        throw new Exception('Phone number is required');
    }
    
    // Нормализация номера телефона
    $phone = preg_replace('/[^0-9]/', '', $data['phone']);
    if (strlen($phone) !== 11) {
        throw new Exception('Invalid phone number format');
    }
    
    // Инициализация сервиса
    $smsService = new SmsService($pdo);
    
    // Отправка кода
    $smsService->sendVerificationCode($phone);
    
    echo json_encode([
        'success' => true,
        'message' => 'Verification code has been sent'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 
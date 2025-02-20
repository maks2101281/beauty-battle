<?php
require_once __DIR__ . '/../config/env.php';

echo "Проверка настроек Telegram бота...\n\n";

$token = Env::get('TELEGRAM_BOT_TOKEN');
$username = Env::get('TELEGRAM_BOT_USERNAME');
$appUrl = Env::get('APP_URL');

// Проверяем токен
echo "Проверка токена бота... ";
$response = file_get_contents("https://api.telegram.org/bot{$token}/getMe");
$result = json_decode($response, true);

if ($result && $result['ok']) {
    echo "OK\n";
    echo "Имя бота: " . $result['result']['first_name'] . "\n";
    echo "Username: @" . $result['result']['username'] . "\n";
} else {
    echo "ОШИБКА\n";
    echo "Ответ API: " . $response . "\n";
}

// Проверяем webhook
echo "\nПроверка webhook... ";
$response = file_get_contents("https://api.telegram.org/bot{$token}/getWebhookInfo");
$result = json_decode($response, true);

if ($result && $result['ok']) {
    echo "OK\n";
    $webhook = $result['result'];
    echo "URL: " . ($webhook['url'] ?: 'Не установлен') . "\n";
    echo "Последняя ошибка: " . ($webhook['last_error_message'] ?: 'Нет') . "\n";
    echo "Ожидающие обновления: " . ($webhook['pending_update_count'] ?? 0) . "\n";
} else {
    echo "ОШИБКА\n";
    echo "Ответ API: " . $response . "\n";
}

// Проверяем права бота
echo "\nПроверка прав бота...\n";
$requiredPermissions = [
    'can_send_messages' => 'Отправка сообщений',
    'can_send_media_messages' => 'Отправка медиа',
    'can_send_other_messages' => 'Отправка других типов сообщений'
];

foreach ($requiredPermissions as $permission => $description) {
    echo "{$description}: " . (isset($result['result'][$permission]) && $result['result'][$permission] ? "OK" : "НЕТ") . "\n";
}

// Проверяем настройки webhook URL
$webhookUrl = $appUrl . '/api/bot/bot.php';
echo "\nПроверка URL для webhook... ";
if (filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
    echo "OK\n";
    echo "URL: {$webhookUrl}\n";
} else {
    echo "ОШИБКА\n";
    echo "Некорректный URL: {$webhookUrl}\n";
} 
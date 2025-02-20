<?php
require_once __DIR__ . '/../config/env.php';

echo "Настройка Telegram бота...\n\n";

$token = '7603535293:AAHCWb3_P9XKHMmaPOOp-dGAcZi35r4KHDs';
$appUrl = Env::get('APP_URL');

// Получаем информацию о боте
echo "Получение информации о боте... ";
$response = file_get_contents("https://api.telegram.org/bot{$token}/getMe");
$result = json_decode($response, true);

if ($result && $result['ok']) {
    echo "OK\n";
    echo "Имя бота: " . $result['result']['first_name'] . "\n";
    echo "Username: @" . $result['result']['username'] . "\n\n";
} else {
    echo "ОШИБКА\n";
    echo "Ответ API: " . $response . "\n";
    exit(1);
}

// Устанавливаем webhook
$webhookUrl = $appUrl . '/api/bot/bot.php';
echo "Установка webhook на {$webhookUrl}... ";

$setWebhookUrl = "https://api.telegram.org/bot{$token}/setWebhook";
$ch = curl_init($setWebhookUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'url' => $webhookUrl,
    'allowed_updates' => json_encode(['message', 'callback_query'])
]);

$response = curl_exec($ch);
$result = json_decode($response, true);

if ($result && $result['ok']) {
    echo "OK\n\n";
} else {
    echo "ОШИБКА\n";
    echo "Ответ API: " . $response . "\n";
    exit(1);
}

// Устанавливаем команды бота
echo "Настройка команд бота... ";
$commands = [
    ['command' => 'start', 'description' => 'Начать использование бота'],
    ['command' => 'help', 'description' => 'Получить помощь'],
    ['command' => 'settings', 'description' => 'Настройки уведомлений']
];

$setCommandsUrl = "https://api.telegram.org/bot{$token}/setMyCommands";
curl_setopt($ch, CURLOPT_URL, $setCommandsUrl);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['commands' => $commands]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$result = json_decode($response, true);

if ($result && $result['ok']) {
    echo "OK\n\n";
} else {
    echo "ОШИБКА\n";
    echo "Ответ API: " . $response . "\n";
    exit(1);
}

curl_close($ch);

// Проверяем настройки
echo "Проверка настроек бота:\n";
$response = file_get_contents("https://api.telegram.org/bot{$token}/getWebhookInfo");
$result = json_decode($response, true);

if ($result && $result['ok']) {
    $webhook = $result['result'];
    echo "- Webhook URL: " . $webhook['url'] . "\n";
    echo "- Последняя ошибка: " . ($webhook['last_error_message'] ?: 'Нет') . "\n";
    echo "- Ожидающие обновления: " . ($webhook['pending_update_count'] ?? 0) . "\n";
} else {
    echo "ОШИБКА при получении информации о webhook\n";
}

echo "\nНастройка бота завершена!\n";
echo "Теперь вы можете использовать бота для авторизации на сайте.\n"; 
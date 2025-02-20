<?php
require_once __DIR__ . '/../../config/env.php';

$token = Env::get('TELEGRAM_BOT_TOKEN');
$appUrl = Env::get('APP_URL');

// Формируем URL для вебхука
$webhookUrl = $appUrl . '/api/bot/bot.php';

// URL для установки вебхука
$apiUrl = "https://api.telegram.org/bot{$token}/setWebhook?url={$webhookUrl}";

// Отправляем запрос
$response = file_get_contents($apiUrl);
$result = json_decode($response, true);

if ($result['ok']) {
    echo "Webhook успешно установлен на URL: {$webhookUrl}\n";
} else {
    echo "Ошибка установки webhook: " . $result['description'] . "\n";
} 
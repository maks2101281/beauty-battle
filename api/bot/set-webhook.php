<?php
$token = getenv('TELEGRAM_BOT_TOKEN');
$webhookUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/api/bot/bot.php';

$url = "https://api.telegram.org/bot{$token}/setWebhook?url={$webhookUrl}";
$response = file_get_contents($url);

echo $response; 
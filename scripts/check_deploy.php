<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../classes/Logger.php';

echo "Проверка развертывания Beauty Battle\n";
echo "===================================\n\n";

try {
    // Проверка переменных окружения
    echo "1. Проверка переменных окружения:\n";
    $required_vars = [
        'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD',
        'TELEGRAM_BOT_TOKEN', 'APP_URL'
    ];
    
    foreach ($required_vars as $var) {
        $value = Env::get($var);
        echo "   {$var}: " . ($value ? "OK" : "ОТСУТСТВУЕТ") . "\n";
        if (!$value) {
            throw new Exception("Отсутствует обязательная переменная окружения: {$var}");
        }
    }
    echo "\n";
    
    // Проверка директорий
    echo "2. Проверка директорий:\n";
    $directories = [
        'public/uploads/photos',
        'public/uploads/videos',
        'public/uploads/thumbnails',
        'cache',
        'logs',
        'public/errors'
    ];
    
    foreach ($directories as $dir) {
        $fullPath = __DIR__ . '/../' . $dir;
        echo "   {$dir}: ";
        if (!file_exists($fullPath)) {
            echo "НЕ СУЩЕСТВУЕТ\n";
            throw new Exception("Директория не существует: {$dir}");
        }
        if (!is_writable($fullPath)) {
            echo "НЕТ ПРАВ НА ЗАПИСЬ\n";
            throw new Exception("Нет прав на запись: {$dir}");
        }
        echo "OK\n";
    }
    echo "\n";
    
    // Проверка подключения к базе данных
    echo "3. Проверка подключения к базе данных:\n";
    try {
        $host = Env::get('DB_HOST');
        if (strpos($host, '.') === false) {
            $host .= '.oregon-postgres.render.com';
        }
        
        $dsn = "pgsql:host={$host};port=5432;dbname=" . Env::get('DB_NAME') . ";sslmode=require";
        $pdo = new PDO($dsn, Env::get('DB_USER'), Env::get('DB_PASSWORD'));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "   Подключение: OK\n";
        
        // Проверка таблиц
        $tables = [
            'telegram_users',
            'verification_codes',
            'auth_tokens',
            'contestants',
            'comments',
            'tournaments'
        ];
        
        foreach ($tables as $table) {
            $stmt = $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
            echo "   Таблица {$table}: OK\n";
        }
    } catch (PDOException $e) {
        echo "   ОШИБКА: " . $e->getMessage() . "\n";
        throw $e;
    }
    echo "\n";
    
    // Проверка Telegram бота
    echo "4. Проверка Telegram бота:\n";
    $token = Env::get('TELEGRAM_BOT_TOKEN');
    $response = file_get_contents("https://api.telegram.org/bot{$token}/getMe");
    $result = json_decode($response, true);
    
    if (!$result['ok']) {
        echo "   ОШИБКА: Не удалось подключиться к боту\n";
        throw new Exception("Ошибка Telegram бота: " . ($result['description'] ?? 'Неизвестная ошибка'));
    }
    
    echo "   Бот активен: OK\n";
    echo "   Имя бота: " . $result['result']['first_name'] . "\n";
    echo "   Username: @" . $result['result']['username'] . "\n\n";
    
    // Проверка webhook
    $webhookUrl = Env::get('APP_URL') . '/api/bot/bot.php';
    $response = file_get_contents("https://api.telegram.org/bot{$token}/getWebhookInfo");
    $webhook = json_decode($response, true);
    
    if ($webhook['result']['url'] !== $webhookUrl) {
        echo "   ВНИМАНИЕ: Webhook URL не соответствует текущему\n";
        echo "   Текущий: " . $webhook['result']['url'] . "\n";
        echo "   Ожидаемый: {$webhookUrl}\n";
        
        // Обновляем webhook
        $setWebhook = file_get_contents("https://api.telegram.org/bot{$token}/setWebhook?url={$webhookUrl}");
        $result = json_decode($setWebhook, true);
        
        if ($result['ok']) {
            echo "   Webhook обновлен: OK\n";
        } else {
            throw new Exception("Не удалось обновить webhook: " . ($result['description'] ?? 'Неизвестная ошибка'));
        }
    } else {
        echo "   Webhook URL: OK\n";
    }
    
    echo "\nПроверка завершена успешно!\n";
    Logger::info('Deployment check completed successfully');
    
} catch (Exception $e) {
    echo "\nОШИБКА: " . $e->getMessage() . "\n";
    Logger::error('Deployment check failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
} 
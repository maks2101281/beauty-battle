<?php
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/database_render.php';
require_once __DIR__ . '/../classes/AuthService.php';

class TelegramBot {
    private $token;
    private $authService;
    private $pdo;
    
    public function __construct(PDO $pdo) {
        $this->token = Env::get('TELEGRAM_BOT_TOKEN');
        $this->pdo = $pdo;
        $this->authService = new AuthService($pdo);
    }
    
    public function handleWebhook() {
        // Получаем данные от Telegram
        $update = json_decode(file_get_contents('php://input'), true);
        
        // Логируем входящие данные если включена отладка
        if (Env::get('APP_DEBUG', false)) {
            file_put_contents(__DIR__ . '/../../logs/telegram.log', 
                date('Y-m-d H:i:s') . ': ' . json_encode($update) . "\n", 
                FILE_APPEND);
        }
        
        if (isset($update['message'])) {
            $message = $update['message'];
            $chatId = $message['chat']['id'];
            
            // Проверяем наличие username
            if (!isset($message['from']['username'])) {
                $this->sendMessage($chatId, 
                    "⚠️ Для использования бота необходимо установить username в Telegram.\n\n" .
                    "1. Откройте настройки Telegram\n" .
                    "2. Выберите 'Изменить профиль'\n" .
                    "3. Укажите имя пользователя (username)\n" .
                    "4. Вернитесь к боту и нажмите /start"
                );
                return;
            }
            
            $username = $message['from']['username'];
            
            // Обработка команд
            if (isset($message['text'])) {
                switch ($message['text']) {
                    case '/start':
                        // Сохраняем или обновляем chat_id пользователя
                        $stmt = $this->pdo->prepare("
                            INSERT INTO telegram_users (username, chat_id, created_at)
                            VALUES (:username, :chat_id, CURRENT_TIMESTAMP)
                            ON CONFLICT (username) 
                            DO UPDATE SET chat_id = :chat_id
                        ");
                        $stmt->execute([
                            'username' => $username,
                            'chat_id' => $chatId
                        ]);
                        
                        $this->sendMessage($chatId, 
                            "👋 Добро пожаловать в Beauty Battle!\n\n" .
                            "Я помогу вам авторизоваться на сайте. " .
                            "Когда будете готовы получить код подтверждения, " .
                            "просто введите свой username на сайте.\n\n" .
                            "Ваш Telegram username: @{$username}\n\n" .
                            "❗️ Важно: не удаляйте и не меняйте свой username до завершения авторизации"
                        );
                        break;

                    case '/help':
                        $this->sendMessage($chatId,
                            "🔍 Помощь по использованию бота:\n\n" .
                            "1. Авторизация:\n" .
                            "   • Перейдите на сайт\n" .
                            "   • Нажмите 'Войти'\n" .
                            "   • Введите свой Telegram username\n" .
                            "   • Получите код в этом чате\n\n" .
                            "2. Голосование:\n" .
                            "   • После авторизации вы можете голосовать\n" .
                            "   • Каждый день можно голосовать заново\n\n" .
                            "3. Предложить участницу:\n" .
                            "   • Нажмите 'Предложить участницу'\n" .
                            "   • Загрузите фото или видео\n" .
                            "   • Заполните информацию\n\n" .
                            "По всем вопросам: @admin"
                        );
                        break;

                    case '/settings':
                        $this->sendMessage($chatId,
                            "⚙️ Настройки уведомлений:\n\n" .
                            "✅ Новые голоса\n" .
                            "✅ Результаты турниров\n" .
                            "✅ Статус предложенных участниц\n\n" .
                            "Скоро здесь появится возможность управления уведомлениями!"
                        );
                        break;

                    default:
                        $this->sendMessage($chatId,
                            "Для использования бота просто введите свой Telegram username на сайте.\n" .
                            "Я автоматически отправлю вам код подтверждения."
                        );
                }
            }
        }
    }
    
    private function sendMessage($chatId, $text) {
        $url = "https://api.telegram.org/bot{$this->token}/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
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
        
        if ($response === false && Env::get('APP_DEBUG', false)) {
            file_put_contents(__DIR__ . '/../../logs/telegram_errors.log', 
                date('Y-m-d H:i:s') . ': Error sending message to ' . $chatId . "\n", 
                FILE_APPEND);
        }
    }
}

// Создаем директорию для логов если включена отладка
if (Env::get('APP_DEBUG', false) && !file_exists(__DIR__ . '/../../logs')) {
    mkdir(__DIR__ . '/../../logs', 0755, true);
}

// Запускаем обработку вебхука
$bot = new TelegramBot($pdo);
$bot->handleWebhook();
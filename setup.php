<?php
echo "🚀 Создание структуры проекта Facemash Battle\n";
echo "=====================================\n\n";

// Структура проекта
$structure = [
    'api' => [
        'bot' => [
            'bot.php' => '<?php
require_once __DIR__ . "/../../config/env.php";
require_once __DIR__ . "/../../config/database_render.php";
require_once __DIR__ . "/../classes/AuthService.php";

class TelegramBot {
    private $token;
    private $authService;
    private $pdo;
    
    public function __construct(PDO $pdo) {
        $this->token = Env::get("TELEGRAM_BOT_TOKEN");
        $this->pdo = $pdo;
        $this->authService = new AuthService($pdo);
    }
    
    // Остальной код бота
}',
            'set-webhook-render.php' => '<?php
require_once __DIR__ . "/../../config/env.php";

$token = Env::get("TELEGRAM_BOT_TOKEN");
$appUrl = Env::get("APP_URL");

$webhookUrl = $appUrl . "/api/bot/bot.php";
$apiUrl = "https://api.telegram.org/bot{$token}/setWebhook?url={$webhookUrl}";'
        ],
        'classes' => [
            'AuthService.php' => '<?php
class AuthService {
    private $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    // Методы авторизации
}',
            'ImageProcessor.php' => '<?php
class ImageProcessor {
    private $maxFileSize;
    private $allowedTypes;
    
    public function __construct() {
        $this->maxFileSize = 5 * 1024 * 1024;
        $this->allowedTypes = ["image/jpeg", "image/png", "image/webp"];
    }
    
    // Методы обработки изображений
}',
            'Cache.php' => '<?php
class Cache {
    private static $cacheDir;
    
    public static function init() {
        self::$cacheDir = __DIR__ . "/../../cache/";
    }
    
    // Методы кэширования
}'
        ],
        'database' => [
            'schema_pg.sql' => '-- Создание таблиц
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);'
        ],
        'comments.php' => '<?php
header("Content-Type: application/json");
require_once __DIR__ . "/../config/database.php";

// API комментариев'
    ],
    'public' => [
        'index.html' => '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Facemash Battle</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <h1>Facemash Battle</h1>
    <script src="js/main.js"></script>
</body>
</html>',
        'css' => [
            'style.css' => '/* Стили */
body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 20px;
}'
        ],
        'js' => [
            'main.js' => '// Основной JavaScript код
document.addEventListener("DOMContentLoaded", function() {
    console.log("App loaded");
});',
            'auth.js' => '// Код авторизации',
            'submit.js' => '// Код отправки форм'
        ]
    ],
    'config' => [
        'database_render.php' => '<?php
require_once __DIR__ . "/env.php";

try {
    $pdo = new PDO(
        "pgsql:host=" . Env::get("DB_HOST") . 
        ";dbname=" . Env::get("DB_NAME"),
        Env::get("DB_USER"),
        Env::get("DB_PASSWORD")
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed");
}',
        'env.php' => '<?php
class Env {
    private static $variables = [];
    
    public static function load($path) {
        if (!file_exists($path)) {
            throw new Exception(".env file not found");
        }
        // Загрузка переменных окружения
    }
    
    public static function get($key, $default = null) {
        return self::$variables[$key] ?? $default;
    }
}',
        'csrf.php' => '<?php
class CSRF {
    public static function generateToken() {
        return bin2hex(random_bytes(32));
    }
    
    // Методы CSRF защиты
}'
    ]
];

// Создание файлов и директорий
function createStructure($structure, $basePath = '') {
    foreach ($structure as $name => $content) {
        $path = $basePath . '/' . $name;
        
        if (is_array($content)) {
            if (!file_exists($path)) {
                mkdir($path, 0755, true);
                echo "✓ Создана директория: {$path}\n";
            }
            createStructure($content, $path);
        } else {
            if (!file_exists($path)) {
                file_put_contents($path, $content);
                echo "✓ Создан файл: {$path}\n";
            }
        }
    }
}

// Создание .gitignore
$gitignore = <<<EOT
.env
uploads/*
!uploads/.gitkeep
cache/*
!cache/.gitkeep
vendor/
composer.lock
.idea/
.vscode/
.DS_Store
Thumbs.db
logs/*
!logs/.gitkeep
EOT;

file_put_contents('.gitignore', $gitignore);
echo "✓ Создан файл .gitignore\n";

// Создание .env.example
$envExample = <<<EOT
DB_HOST=your_database_host
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASSWORD=your_database_password

TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_BOT_USERNAME=your_bot_username

APP_URL=https://your-app-url.onrender.com
APP_ENV=production
APP_DEBUG=false
EOT;

file_put_contents('.env.example', $envExample);
echo "✓ Создан файл .env.example\n";

// Создание composer.json
$composer = [
    'name' => 'facemash/battle',
    'description' => 'Facemash Battle - платформа для голосования',
    'type' => 'project',
    'require' => [
        'php' => '>=7.4',
        'ext-pdo' => '*',
        'ext-json' => '*',
        'ext-gd' => '*',
        'ext-curl' => '*'
    ]
];

file_put_contents('composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "✓ Создан файл composer.json\n";

// Создание структуры проекта
createStructure($structure);

echo "\n✨ Структура проекта создана!\n";
echo "\nТеперь выполните:\n";
echo "1. git add .\n";
echo "2. git commit -m \"Add project files\"\n";
echo "3. git push origin master\n"; 
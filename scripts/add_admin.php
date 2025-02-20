<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database_render.php';

if (php_sapi_name() !== 'cli') {
    die('Этот скрипт можно запустить только из командной строки');
}

// Получаем username из аргументов командной строки
$username = $argv[1] ?? null;

if (!$username) {
    die("Использование: php add_admin.php <telegram_username>\n");
}

try {
    // Проверяем существование пользователя
    $stmt = $pdo->prepare("
        SELECT id FROM telegram_users 
        WHERE username = ?
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        die("Пользователь с username {$username} не найден\n");
    }

    // Делаем пользователя администратором
    $stmt = $pdo->prepare("
        UPDATE telegram_users 
        SET is_admin = true 
        WHERE username = ?
    ");
    $stmt->execute([$username]);

    echo "Пользователь {$username} успешно назначен администратором\n";

} catch (PDOException $e) {
    die("Ошибка базы данных: " . $e->getMessage() . "\n");
} 
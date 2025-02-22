<?php
// Простой PHP сервер для локальной разработки
$host = 'localhost';
$port = 8000;
$root = __DIR__ . '/public';

echo "Запуск сервера на http://{$host}:{$port}\n";
echo "Корневая директория: {$root}\n";
echo "Нажмите Ctrl+C для остановки\n\n";

// Запускаем встроенный PHP сервер
$command = sprintf('php -S %s:%d -t %s', $host, $port, $root);
system($command); 
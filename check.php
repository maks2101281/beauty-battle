<?php
header('Content-Type: text/plain');

echo "Beauty Battle - Проверка конфигурации\n";
echo "=====================================\n\n";

// Проверка версии PHP
echo "PHP версия: " . phpversion() . "\n";
echo "Рекомендуемая версия: 7.4 или выше\n\n";

// Проверка расширений
$required_extensions = [
    'pdo',
    'pdo_mysql',
    'gd',
    'json',
    'mbstring',
    'openssl',
    'curl'
];

echo "Проверка расширений:\n";
foreach ($required_extensions as $ext) {
    echo $ext . ": " . (extension_loaded($ext) ? "OK" : "НЕ УСТАНОВЛЕНО") . "\n";
}
echo "\n";

// Проверка прав доступа
$directories = [
    'uploads/photos',
    'uploads/thumbnails',
    'cache'
];

echo "Проверка прав доступа:\n";
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    echo $dir . ": " . (is_writable($dir) ? "OK" : "НЕТ ДОСТУПА") . "\n";
}
echo "\n";

// Проверка настроек PHP
echo "Настройки PHP:\n";
$settings = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time')
];

foreach ($settings as $key => $value) {
    echo $key . ": " . $value . "\n";
}
echo "\n";

// Проверка подключения к базе данных
echo "Проверка подключения к БД:\n";
try {
    require_once 'config/database.php';
    echo "Подключение к БД: OK\n";
} catch (Exception $e) {
    echo "Ошибка подключения к БД: " . $e->getMessage() . "\n";
}
echo "\n";

// Проверка кэширования
echo "Проверка кэширования:\n";
require_once 'classes/Cache.php';
try {
    $testKey = 'test_' . time();
    $testData = 'test_data';
    
    Cache::set($testKey, $testData);
    $result = Cache::get($testKey);
    
    echo "Запись в кэш: " . ($result === $testData ? "OK" : "ОШИБКА") . "\n";
    Cache::delete($testKey);
} catch (Exception $e) {
    echo "Ошибка кэширования: " . $e->getMessage() . "\n";
}
echo "\n";

// Проверка обработки изображений
echo "Проверка обработки изображений:\n";
try {
    $gd_info = gd_info();
    echo "GD версия: " . $gd_info['GD Version'] . "\n";
    echo "Поддержка JPEG: " . ($gd_info['JPEG Support'] ? "OK" : "НЕТ") . "\n";
    echo "Поддержка PNG: " . ($gd_info['PNG Support'] ? "OK" : "НЕТ") . "\n";
    echo "Поддержка WebP: " . ($gd_info['WebP Support'] ? "OK" : "НЕТ") . "\n";
} catch (Exception $e) {
    echo "Ошибка проверки GD: " . $e->getMessage() . "\n";
}

echo "\nПроверка завершена\n"; 
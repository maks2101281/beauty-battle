<?php
require_once __DIR__ . '/../config/env.php';

echo "Проверка директорий для загрузки файлов...\n\n";

$directories = [
    'public/uploads',
    'public/uploads/photos',
    'public/uploads/videos',
    'public/uploads/thumbnails',
    'cache',
    'logs'
];

foreach ($directories as $dir) {
    $fullPath = __DIR__ . '/../' . $dir;
    
    if (!file_exists($fullPath)) {
        echo "Создание директории {$dir}... ";
        if (mkdir($fullPath, 0755, true)) {
            echo "OK\n";
        } else {
            echo "ОШИБКА\n";
        }
    } else {
        echo "Директория {$dir} существует... ";
        if (is_writable($fullPath)) {
            echo "Доступна для записи\n";
        } else {
            echo "НЕТ ПРАВ НА ЗАПИСЬ\n";
            // Пытаемся исправить права
            chmod($fullPath, 0755);
            echo "Попытка исправить права... " . (is_writable($fullPath) ? "OK" : "ОШИБКА") . "\n";
        }
    }
}

// Проверяем настройки PHP для загрузки файлов
echo "\nНастройки PHP:\n";
$settings = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_file_uploads' => ini_get('max_file_uploads'),
    'memory_limit' => ini_get('memory_limit')
];

foreach ($settings as $key => $value) {
    echo "{$key}: {$value}\n";
}

// Проверяем расширения для обработки изображений
echo "\nПроверка расширений:\n";
$extensions = [
    'gd' => 'GD',
    'fileinfo' => 'FileInfo',
    'exif' => 'EXIF'
];

foreach ($extensions as $ext => $name) {
    echo "{$name}: " . (extension_loaded($ext) ? "OK" : "ОТСУТСТВУЕТ") . "\n";
    if ($ext === 'gd' && extension_loaded($ext)) {
        $info = gd_info();
        echo "  Поддержка JPEG: " . ($info['JPEG Support'] ? "Да" : "Нет") . "\n";
        echo "  Поддержка PNG: " . ($info['PNG Support'] ? "Да" : "Нет") . "\n";
        echo "  Поддержка WebP: " . ($info['WebP Support'] ? "Да" : "Нет") . "\n";
    }
} 
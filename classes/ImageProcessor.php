<?php
require_once __DIR__ . '/../config/env.php';

class ImageProcessor {
    private const MAX_WIDTH = 1920;
    private const MAX_HEIGHT = 1080;
    private const THUMB_WIDTH = 300;
    private const THUMB_HEIGHT = 300;
    private const QUALITY = 85;
    private const MAX_FILE_SIZE = 5242880; // 5MB

    private $maxFileSize;
    private $allowedTypes;
    private $maxWidth;
    private $maxHeight;
    private $quality;
    
    public function __construct() {
        $this->maxFileSize = Env::get('UPLOAD_MAX_SIZE', 2 * 1024 * 1024); // 2MB по умолчанию
        $this->allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $this->maxWidth = 800;  // Уменьшаем максимальные размеры
        $this->maxHeight = 800;
        $this->quality = Env::get('IMAGE_QUALITY', 70);
        
        // Устанавливаем ограничение времени выполнения
        set_time_limit(Env::get('SCRIPT_TIMEOUT', 30));
    }
    
    /**
     * Проверяет изображение на соответствие требованиям
     */
    public function validateImage($file) {
        // Проверка размера файла
        if ($file['size'] > self::MAX_FILE_SIZE) {
            throw new Exception('Размер файла превышает допустимый (5MB)');
        }

        // Проверка формата
        $image = @imagecreatefromstring(file_get_contents($file['tmp_name']));
        if (!$image) {
            throw new Exception('Некорректный формат изображения');
        }

        // Проверка размеров
        $width = imagesx($image);
        $height = imagesy($image);
        
        if ($width < 100 || $height < 100) {
            throw new Exception('Изображение слишком маленькое (минимум 100x100)');
        }

        imagedestroy($image);
    }
    
    /**
     * Обрабатывает и оптимизирует изображение
     */
    public function processImage($sourcePath, $destinationPath) {
        // Загружаем изображение
        $image = imagecreatefromstring(file_get_contents($sourcePath));
        if (!$image) {
            throw new Exception('Ошибка при обработке изображения');
        }

        // Получаем размеры
        $width = imagesx($image);
        $height = imagesy($image);

        // Вычисляем новые размеры с сохранением пропорций
        if ($width > self::MAX_WIDTH || $height > self::MAX_HEIGHT) {
            $ratio = min(self::MAX_WIDTH / $width, self::MAX_HEIGHT / $height);
            $newWidth = round($width * $ratio);
            $newHeight = round($height * $ratio);

            // Создаем новое изображение
            $newImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Включаем сглаживание
            imageantialias($newImage, true);
            
            // Копируем и ресайзим изображение
            imagecopyresampled(
                $newImage, $image,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $width, $height
            );
            
            // Освобождаем память
            imagedestroy($image);
            $image = $newImage;
        }

        // Определяем формат для сохранения
        $extension = pathinfo($destinationPath, PATHINFO_EXTENSION);
        
        // Сохраняем оптимизированное изображение
        switch (strtolower($extension)) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($image, $destinationPath, self::QUALITY);
                break;
            case 'png':
                // Устанавливаем уровень сжатия PNG (0-9)
                imagesavealpha($image, true);
                imagepng($image, $destinationPath, 6);
                break;
            case 'webp':
                imagewebp($image, $destinationPath, self::QUALITY);
                break;
            default:
                throw new Exception('Неподдерживаемый формат изображения');
        }

        // Освобождаем память
        imagedestroy($image);

        // Устанавливаем права на файл
        chmod($destinationPath, 0644);
    }
    
    /**
     * Создает миниатюру изображения
     */
    public function generateThumbnail($sourcePath, $destinationPath) {
        // Загружаем изображение
        $image = imagecreatefromstring(file_get_contents($sourcePath));
        if (!$image) {
            throw new Exception('Ошибка при создании миниатюры');
        }

        // Получаем размеры
        $width = imagesx($image);
        $height = imagesy($image);

        // Вычисляем размеры для обрезки
        $ratio = max(self::THUMB_WIDTH / $width, self::THUMB_HEIGHT / $height);
        $newWidth = round($width * $ratio);
        $newHeight = round($height * $ratio);

        // Создаем временное изображение
        $tempImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Включаем сглаживание
        imageantialias($tempImage, true);
        
        // Ресайзим
        imagecopyresampled(
            $tempImage, $image,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $width, $height
        );
        
        // Освобождаем память
        imagedestroy($image);

        // Вычисляем координаты для обрезки
        $x = ($newWidth - self::THUMB_WIDTH) / 2;
        $y = ($newHeight - self::THUMB_HEIGHT) / 2;

        // Создаем финальную миниатюру
        $thumbnail = imagecreatetruecolor(self::THUMB_WIDTH, self::THUMB_HEIGHT);
        
        // Копируем и обрезаем изображение
        imagecopy(
            $thumbnail, $tempImage,
            0, 0, $x, $y,
            self::THUMB_WIDTH, self::THUMB_HEIGHT
        );
        
        // Освобождаем память
        imagedestroy($tempImage);

        // Сохраняем миниатюру
        $extension = pathinfo($destinationPath, PATHINFO_EXTENSION);
        switch (strtolower($extension)) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($thumbnail, $destinationPath, self::QUALITY);
                break;
            case 'png':
                imagesavealpha($thumbnail, true);
                imagepng($thumbnail, $destinationPath, 6);
                break;
            case 'webp':
                imagewebp($thumbnail, $destinationPath, self::QUALITY);
                break;
        }

        // Освобождаем память
        imagedestroy($thumbnail);

        // Устанавливаем права на файл
        chmod($destinationPath, 0644);
    }
} 
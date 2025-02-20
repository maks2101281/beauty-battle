<?php
require_once __DIR__ . '/../config/env.php';

class ImageProcessor {
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
    
    public function validateImage($file) {
        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('Файл слишком большой. Максимальный размер: ' . 
                              round($this->maxFileSize / 1024 / 1024, 1) . 'MB');
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            throw new Exception('Недопустимый тип файла. Разрешены только: JPEG, PNG, WebP');
        }
        
        // Проверяем размеры изображения
        list($width, $height) = getimagesize($file['tmp_name']);
        if ($width > 2000 || $height > 2000) {
            throw new Exception('Слишком большие размеры изображения. Максимум: 2000x2000 пикселей');
        }
        
        return true;
    }
    
    public function processImage($source, $destination) {
        // Проверяем доступность памяти
        $imageInfo = getimagesize($source);
        $memoryNeeded = $imageInfo[0] * $imageInfo[1] * 4 * 1.7; // Примерный расчет необходимой памяти
        
        if (memory_get_usage() + $memoryNeeded > memory_get_limit()) {
            throw new Exception('Недостаточно памяти для обработки изображения');
        }
        
        try {
            list($width, $height, $type) = $imageInfo;
            
            // Вычисляем новые размеры с сохранением пропорций
            $ratio = min($this->maxWidth / $width, $this->maxHeight / $height);
            $newWidth = round($width * $ratio);
            $newHeight = round($height * $ratio);
            
            // Создаем новое изображение
            $newImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Загружаем исходное изображение
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $source = imagecreatefromjpeg($source);
                    break;
                case IMAGETYPE_PNG:
                    $source = imagecreatefrompng($source);
                    imagealphablending($newImage, false);
                    imagesavealpha($newImage, true);
                    break;
                case IMAGETYPE_WEBP:
                    $source = imagecreatefromwebp($source);
                    break;
                default:
                    throw new Exception('Неподдерживаемый формат изображения');
            }
            
            // Изменяем размер
            imagecopyresampled(
                $newImage, $source,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $width, $height
            );
            
            // Создаем директорию, если не существует
            $dir = dirname($destination);
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
            
            // Сохраняем оптимизированное изображение
            switch ($type) {
                case IMAGETYPE_JPEG:
                    imagejpeg($newImage, $destination, $this->quality);
                    break;
                case IMAGETYPE_PNG:
                    imagepng($newImage, $destination, round(9 * $this->quality / 100));
                    break;
                case IMAGETYPE_WEBP:
                    imagewebp($newImage, $destination, $this->quality);
                    break;
            }
            
            // Освобождаем память
            imagedestroy($source);
            imagedestroy($newImage);
            
            return true;
            
        } catch (Exception $e) {
            // Освобождаем память в случае ошибки
            if (isset($source) && is_resource($source)) {
                imagedestroy($source);
            }
            if (isset($newImage) && is_resource($newImage)) {
                imagedestroy($newImage);
            }
            throw $e;
        }
    }
    
    public function generateThumbnail($source, $destination, $thumbWidth = 150, $thumbHeight = 150) {
        try {
            list($width, $height) = getimagesize($source);
            
            // Вычисляем размеры для обрезки
            $ratio = max($thumbWidth / $width, $thumbHeight / $height);
            $newWidth = round($width * $ratio);
            $newHeight = round($height * $ratio);
            
            $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
            $source = imagecreatefromjpeg($source);
            
            // Изменяем размер и обрезаем
            imagecopyresampled(
                $thumbnail, $source,
                0, 0,
                ($newWidth - $thumbWidth) / 2, ($newHeight - $thumbHeight) / 2,
                $thumbWidth, $thumbHeight,
                $width, $height
            );
            
            // Создаем директорию, если не существует
            $dir = dirname($destination);
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
            
            // Сохраняем миниатюру
            imagejpeg($thumbnail, $destination, $this->quality);
            
            imagedestroy($source);
            imagedestroy($thumbnail);
            
            return true;
            
        } catch (Exception $e) {
            if (isset($source) && is_resource($source)) {
                imagedestroy($source);
            }
            if (isset($thumbnail) && is_resource($thumbnail)) {
                imagedestroy($thumbnail);
            }
            throw $e;
        }
    }
} 
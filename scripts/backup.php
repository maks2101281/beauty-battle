<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../classes/Logger.php';

class DatabaseBackup {
    private static $backupDir;
    private static $maxBackups = 10;
    
    public static function init() {
        self::$backupDir = __DIR__ . '/../backups/';
        if (!file_exists(self::$backupDir)) {
            mkdir(self::$backupDir, 0755, true);
        }
    }
    
    public static function create() {
        self::init();
        
        try {
            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $filepath = self::$backupDir . $filename;
            
            // Формируем команду для бэкапа
            $command = sprintf(
                'pg_dump -U %s -h %s %s > %s',
                Env::get('DB_USER'),
                Env::get('DB_HOST'),
                Env::get('DB_NAME'),
                $filepath
            );
            
            // Выполняем команду
            exec($command, $output, $returnVar);
            
            if ($returnVar !== 0) {
                throw new Exception('Backup failed with error code: ' . $returnVar);
            }
            
            // Сжимаем файл
            $gzFilepath = $filepath . '.gz';
            $gz = gzopen($gzFilepath, 'w9');
            gzwrite($gz, file_get_contents($filepath));
            gzclose($gz);
            
            // Удаляем несжатый файл
            unlink($filepath);
            
            // Очищаем старые бэкапы
            self::cleanup();
            
            Logger::info('Database backup created successfully', ['file' => $gzFilepath]);
            return true;
            
        } catch (Exception $e) {
            Logger::error('Database backup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    private static function cleanup() {
        $files = glob(self::$backupDir . '*.sql.gz');
        if (count($files) > self::$maxBackups) {
            // Сортируем файлы по времени создания
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Удаляем самые старые файлы
            $filesToDelete = array_slice($files, 0, count($files) - self::$maxBackups);
            foreach ($filesToDelete as $file) {
                unlink($file);
                Logger::info('Old backup deleted', ['file' => $file]);
            }
        }
    }
}

// Запускаем бэкап
if (php_sapi_name() === 'cli') {
    DatabaseBackup::create();
} 
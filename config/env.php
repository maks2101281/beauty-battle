<?php

class Env {
    private static $variables = [];

    public static function load($path = null) {
        // Сначала загружаем из файла, если он есть
        if ($path && file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0) continue;
                
                list($name, $value) = explode('=', $line, 2);
                self::$variables[trim($name)] = trim($value);
            }
        }

        // Затем загружаем из переменных окружения (они имеют приоритет)
        foreach ($_ENV as $key => $value) {
            self::$variables[$key] = $value;
        }

        // Также проверяем getenv() для переменных из Render
        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'APP_URL', 'PORT'] as $key) {
            $value = getenv($key);
            if ($value !== false) {
                self::$variables[$key] = $value;
            }
        }
    }

    public static function get($key, $default = null) {
        // Если переменные еще не загружены
        if (empty(self::$variables)) {
            self::load(__DIR__ . '/../.env');
        }

        // Сначала проверяем прямой getenv() для Render
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return self::$variables[$key] ?? $default;
    }

    public static function dump() {
        // Загружаем переменные, если еще не загружены
        if (empty(self::$variables)) {
            self::load(__DIR__ . '/../.env');
        }

        echo "<pre>\n";
        echo "=== Environment Variables ===\n\n";
        
        // Вывод переменных из self::$variables
        echo "From variables array:\n";
        foreach (self::$variables as $key => $value) {
            if (strpos(strtolower($key), 'password') !== false) {
                $value = '********';
            }
            echo "$key = $value\n";
        }
        
        echo "\nDirect environment check:\n";
        $important_vars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'APP_URL', 'PORT'];
        foreach ($important_vars as $key) {
            $value = getenv($key);
            echo "$key = " . ($value === false ? 'not set' : $value) . "\n";
        }
        
        echo "\n=== Server Variables ===\n\n";
        echo "DOCUMENT_ROOT = " . $_SERVER['DOCUMENT_ROOT'] . "\n";
        echo "SCRIPT_FILENAME = " . $_SERVER['SCRIPT_FILENAME'] . "\n";
        echo "REQUEST_URI = " . $_SERVER['REQUEST_URI'] . "\n";
        
        echo "</pre>";
    }
}

// Загружаем переменные окружения
Env::load(__DIR__ . '/../.env'); 
<?php

class Env {
    private static $variables = [];

    public static function load($path) {
        if (!file_exists($path)) {
            throw new Exception('.env file not found');
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) continue;
            
            list($name, $value) = explode('=', $line, 2);
            self::$variables[trim($name)] = trim($value);
        }
    }

    public static function get($key, $default = null) {
        return self::$variables[$key] ?? $default;
    }
}

// Загружаем переменные окружения
Env::load(__DIR__ . '/../.env'); 
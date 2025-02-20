<?php
require_once __DIR__ . '/../config/env.php';

class Cache {
    private static $cacheDir;
    private static $enabled;
    private static $defaultLifetime;
    
    public static function init() {
        self::$cacheDir = __DIR__ . '/../cache/';
        self::$enabled = Env::get('CACHE_ENABLED', false);
        self::$defaultLifetime = Env::get('CACHE_LIFETIME', 3600);
        
        if (self::$enabled && !file_exists(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }
    
    public static function set($key, $data, $lifetime = null) {
        if (!self::$enabled) return false;
        
        $lifetime = $lifetime ?? self::$defaultLifetime;
        $cacheFile = self::getCacheFile($key);
        
        $cacheData = [
            'expires' => time() + $lifetime,
            'data' => $data
        ];
        
        return file_put_contents($cacheFile, serialize($cacheData)) !== false;
    }
    
    public static function get($key) {
        if (!self::$enabled) return null;
        
        $cacheFile = self::getCacheFile($key);
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $cacheData = unserialize(file_get_contents($cacheFile));
        
        if ($cacheData['expires'] < time()) {
            unlink($cacheFile);
            return null;
        }
        
        return $cacheData['data'];
    }
    
    public static function delete($key) {
        if (!self::$enabled) return false;
        
        $cacheFile = self::getCacheFile($key);
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        return false;
    }
    
    public static function clear() {
        if (!self::$enabled) return false;
        
        $files = glob(self::$cacheDir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return true;
    }
    
    private static function getCacheFile($key) {
        return self::$cacheDir . md5($key) . '.cache';
    }
}

// Инициализация кэша
Cache::init(); 
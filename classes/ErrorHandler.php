<?php

class ErrorHandler {
    private static $instance = null;
    private $logger;
    private $displayErrors;

    private function __construct() {
        $this->logger = new Logger();
        $this->displayErrors = getenv('APP_DEBUG') === 'true';

        // Устанавливаем обработчики
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Обработка PHP ошибок
     */
    public function handleError($level, $message, $file, $line) {
        if (error_reporting() & $level) {
            $error = [
                'type' => $this->getErrorType($level),
                'message' => $message,
                'file' => $file,
                'line' => $line
            ];

            // Логируем ошибку
            $this->logger->error('PHP Error', $error);

            // Отправляем ответ клиенту
            $this->sendErrorResponse($error);

            return true;
        }

        return false;
    }

    /**
     * Обработка исключений
     */
    public function handleException($exception) {
        $error = [
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];

        // Логируем исключение
        $this->logger->error('Uncaught Exception', $error);

        // Отправляем ответ клиенту
        $this->sendErrorResponse($error);
    }

    /**
     * Обработка фатальных ошибок
     */
    public function handleShutdown() {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $this->handleError(
                $error['type'],
                $error['message'],
                $error['file'],
                $error['line']
            );
        }
    }

    /**
     * Отправка ответа об ошибке клиенту
     */
    private function sendErrorResponse($error) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }

        $response = [
            'success' => false,
            'error' => $this->displayErrors ? $error : 'Внутренняя ошибка сервера'
        ];

        if (php_sapi_name() !== 'cli') {
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Получение типа ошибки в читаемом виде
     */
    private function getErrorType($type) {
        switch($type) {
            case E_ERROR:
                return 'E_ERROR';
            case E_WARNING:
                return 'E_WARNING';
            case E_PARSE:
                return 'E_PARSE';
            case E_NOTICE:
                return 'E_NOTICE';
            case E_CORE_ERROR:
                return 'E_CORE_ERROR';
            case E_CORE_WARNING:
                return 'E_CORE_WARNING';
            case E_COMPILE_ERROR:
                return 'E_COMPILE_ERROR';
            case E_COMPILE_WARNING:
                return 'E_COMPILE_WARNING';
            case E_USER_ERROR:
                return 'E_USER_ERROR';
            case E_USER_WARNING:
                return 'E_USER_WARNING';
            case E_USER_NOTICE:
                return 'E_USER_NOTICE';
            case E_STRICT:
                return 'E_STRICT';
            case E_RECOVERABLE_ERROR:
                return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED:
                return 'E_DEPRECATED';
            case E_USER_DEPRECATED:
                return 'E_USER_DEPRECATED';
            default:
                return 'UNKNOWN';
        }
    }

    /**
     * Проверка здоровья приложения
     */
    public function checkHealth() {
        try {
            // Проверяем подключение к базе данных
            $db = Database::getInstance();
            $db->query('SELECT 1');

            // Проверяем директории
            $directories = [
                __DIR__ . '/../public/uploads',
                __DIR__ . '/../logs',
                __DIR__ . '/../cache'
            ];

            foreach ($directories as $dir) {
                if (!is_dir($dir) || !is_writable($dir)) {
                    throw new Exception("Директория {$dir} недоступна для записи");
                }
            }

            // Проверяем PHP расширения
            $requiredExtensions = ['pdo', 'pdo_pgsql', 'gd', 'json'];
            foreach ($requiredExtensions as $ext) {
                if (!extension_loaded($ext)) {
                    throw new Exception("PHP расширение {$ext} не установлено");
                }
            }

            return [
                'status' => 'healthy',
                'timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
} 
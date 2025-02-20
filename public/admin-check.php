<?php
require_once __DIR__ . '/../api/middleware/AdminMiddleware.php';

// Проверяем права доступа
AdminMiddleware::checkAccess();

// Если проверка пройдена, отдаем админ-панель
readfile(__DIR__ . '/admin.html'); 
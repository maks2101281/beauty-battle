<?php
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line');
}

// Путь к PHP
$phpPath = PHP_BINARY;

// Путь к директории проекта
$projectPath = dirname(__DIR__);

// Задачи для cron
$tasks = [
    // Бэкап базы данных каждый день в 2 часа ночи
    [
        'time' => '0 2 * * *',
        'command' => "{$phpPath} {$projectPath}/scripts/backup.php"
    ],
    
    // Очистка старых логов каждую неделю
    [
        'time' => '0 3 * * 0',
        'command' => "{$phpPath} {$projectPath}/scripts/cleanup_logs.php"
    ],
    
    // Очистка устаревших сессий каждый час
    [
        'time' => '0 * * * *',
        'command' => "{$phpPath} {$projectPath}/scripts/cleanup_sessions.php"
    ],
    
    // Сбор метрик каждые 5 минут
    [
        'time' => '*/5 * * * *',
        'command' => "{$phpPath} {$projectPath}/scripts/collect_metrics.php"
    ]
];

// Получаем текущие cron задачи
exec('crontab -l', $currentCron);

// Добавляем наши задачи
foreach ($tasks as $task) {
    $cronLine = "{$task['time']} {$task['command']} >> {$projectPath}/logs/cron.log 2>&1";
    $currentCron[] = $cronLine;
}

// Сохраняем временный файл
$tempFile = tempnam(sys_get_temp_dir(), 'cron');
file_put_contents($tempFile, implode("\n", $currentCron) . "\n");

// Устанавливаем новый crontab
exec("crontab {$tempFile}");
unlink($tempFile);

echo "Cron tasks installed successfully!\n"; 
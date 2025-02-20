<?php
require_once __DIR__ . '/../config/env.php';

$host = getenv('DB_HOST') ?: Env::get('DB_HOST');
if (strpos($host, '.') === false) {
    $host .= '.oregon-postgres.render.com';
}

echo "Checking SSL connection to {$host}...\n\n";

// Проверяем наличие сертификата
$certPath = '/etc/ssl/certs/ca-certificates.crt';
if (!file_exists($certPath)) {
    echo "Error: SSL certificate not found at {$certPath}\n";
    exit(1);
}

// Проверяем SSL соединение
$context = stream_context_create([
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'cafile' => $certPath
    ]
]);

echo "SSL Certificate info:\n";
echo "Certificate path: {$certPath}\n";
echo "Certificate exists: " . (file_exists($certPath) ? "Yes" : "No") . "\n";
echo "Certificate readable: " . (is_readable($certPath) ? "Yes" : "No") . "\n\n";

try {
    $socket = @stream_socket_client(
        "ssl://{$host}:5432",
        $errno,
        $errstr,
        30,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if ($socket) {
        echo "SSL connection successful!\n";
        $cert = stream_context_get_params($socket);
        print_r($cert['options']['ssl']['peer_certificate']);
        fclose($socket);
    } else {
        echo "Connection failed: {$errstr} ({$errno})\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 
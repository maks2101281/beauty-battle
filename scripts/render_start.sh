#!/bin/bash
set -e

echo "Starting Beauty Battle initialization..."

# Проверка переменных окружения
echo "Checking environment variables..."
required_vars=(
    "DB_HOST"
    "DB_NAME"
    "DB_USER"
    "DB_PASSWORD"
    "TELEGRAM_BOT_TOKEN"
    "APP_URL"
)

for var in "${required_vars[@]}"; do
    if [ -z "${!var}" ]; then
        echo "Error: $var is not set"
        exit 1
    fi
done
echo "Environment variables OK"

# Создание необходимых директорий
echo "Creating required directories..."
directories=(
    "public/uploads/photos"
    "public/uploads/videos"
    "public/uploads/thumbnails"
    "cache"
    "logs"
    "public/errors"
)

for dir in "${directories[@]}"; do
    if [ ! -d "$dir" ]; then
        echo "Creating directory: $dir"
        mkdir -p "$dir"
    fi
done
echo "Directories created"

# Настройка прав доступа
echo "Setting permissions..."
chown -R www-data:www-data .
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod -R 777 public/uploads cache logs
chmod +x scripts/*
echo "Permissions set"

# Проверка SSL сертификатов
echo "Checking SSL certificates..."
if [ ! -d "/var/www/.postgresql" ]; then
    mkdir -p /var/www/.postgresql
fi

if [ -f "/etc/ssl/certs/ca-certificates.crt" ]; then
    cp /etc/ssl/certs/ca-certificates.crt /var/www/.postgresql/root.crt
    echo "SSL certificate copied"
else
    echo "Warning: SSL certificate not found"
fi

# Проверка подключения к базе данных
echo "Checking database connection..."
max_tries=30
tries=0

while [ $tries -lt $max_tries ]; do
    if php -r "
        \$host = getenv('DB_HOST');
        if (strpos(\$host, '.') === false) {
            \$host .= '.oregon-postgres.render.com';
        }
        \$dbname = getenv('DB_NAME');
        \$user = getenv('DB_USER');
        \$pass = getenv('DB_PASSWORD');
        \$dsn = \"pgsql:host=\$host;port=5432;dbname=\$dbname;sslmode=require\";
        try {
            new PDO(\$dsn, \$user, \$pass);
            echo 'connected';
        } catch (PDOException \$e) {
            exit(1);
        }
    " 2>/dev/null | grep -q 'connected'; then
        echo "Database connection successful"
        break
    fi
    
    tries=$((tries + 1))
    echo "Database connection attempt $tries of $max_tries"
    sleep 2
done

if [ $tries -eq $max_tries ]; then
    echo "Error: Could not connect to database"
    exit 1
fi

# Инициализация базы данных
echo "Initializing database..."
php scripts/init_render_db.php

# Проверка развертывания
echo "Running deployment checks..."
php scripts/check_deploy.php

if [ $? -ne 0 ]; then
    echo "Deployment check failed"
    exit 1
fi

echo "Initialization completed successfully"

# Запуск Apache
echo "Starting Apache..."
exec apache2-foreground 
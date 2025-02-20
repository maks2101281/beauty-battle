#!/bin/bash

echo "Starting Beauty Battle initialization..."

# Проверка переменных окружения
echo "Checking environment variables..."
required_vars=("DB_HOST" "DB_NAME" "DB_USER" "DB_PASSWORD" "TELEGRAM_BOT_TOKEN" "APP_URL")
for var in "${required_vars[@]}"; do
    if [ -z "${!var}" ]; then
        echo "Error: $var is not set"
        exit 1
    fi
done
echo "Environment variables OK"

# Создание необходимых директорий
echo "Creating required directories..."
mkdir -p /var/www/html/public/uploads/photos
mkdir -p /var/www/html/public/uploads/videos
mkdir -p /var/www/html/public/uploads/thumbnails
mkdir -p /var/www/html/cache
mkdir -p /var/www/html/logs
mkdir -p /var/www/.postgresql
echo "Directories created"

# Установка прав доступа
echo "Setting permissions..."
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html
chmod -R 777 /var/www/html/public/uploads
chmod -R 777 /var/www/html/cache
chmod -R 777 /var/www/html/logs
chmod -R 777 /var/www/.postgresql
echo "Permissions set"

# Проверка SSL сертификатов
echo "Checking SSL certificates..."
if [ -f "/etc/ssl/certs/ca-certificates.crt" ]; then
    cp /etc/ssl/certs/ca-certificates.crt /var/www/.postgresql/root.crt
    echo "SSL certificate copied"
else
    echo "Warning: SSL certificate not found"
fi

# Инициализация базы данных
echo "Initializing database..."
php scripts/init_render_db.php
echo "Database initialized"

# Проверка базы данных
echo "Running database checks..."
php scripts/check_database.php
echo "Database checks completed"

# Проверка загрузки файлов
echo "Checking file upload configuration..."
php scripts/check_uploads.php
echo "File upload checks completed"

# Настройка Telegram бота
echo "Setting up Telegram bot..."
php scripts/check_telegram.php
echo "Telegram bot setup completed"

# Настройка Apache
echo "Configuring Apache..."
sed -i "s/\${PORT}/$PORT/g" /etc/apache2/sites-available/000-default.conf
echo "Apache configured"

# Запуск Apache
echo "Starting Apache..."
apache2-foreground 
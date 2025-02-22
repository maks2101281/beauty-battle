#!/bin/bash
set -e

# Определяем базовую директорию
BASE_DIR="/var/www/html"
cd $BASE_DIR

# Функция для проверки доступности базы данных
wait_for_db() {
    echo "Waiting for database..."
    max_tries=30
    tries=0
    
    while [ $tries -lt $max_tries ]; do
        if php -r "
            \$host = getenv('DB_HOST');
            if (strpos(\$host, '.') === false) {
                \$host .= '.frankfurt-postgres.render.com';
            }
            \$dbname = getenv('DB_NAME');
            \$user = getenv('DB_USER');
            \$pass = getenv('DB_PASSWORD');
            \$dsn = \"pgsql:host=\$host;port=5432;dbname=\$dbname;sslmode=require\";
            try {
                \$pdo = new PDO(\$dsn, \$user, \$pass);
                \$pdo->query('SELECT 1');
                echo 'connected';
            } catch (PDOException \$e) {
                error_log('DB Connection error: ' . \$e->getMessage());
                exit(1);
            }
        " 2>/dev/null | grep -q 'connected'; then
            echo "Database is available"
            return 0
        fi
        
        tries=$((tries + 1))
        echo "Attempt $tries of $max_tries"
        sleep 2
    done
    
    echo "Database is not available after $max_tries attempts"
    return 1
}

# Функция для настройки директорий
setup_directories() {
    echo "Setting up directories..."
    directories=(
        "${BASE_DIR}/public/uploads/photos"
        "${BASE_DIR}/public/uploads/videos"
        "${BASE_DIR}/public/uploads/thumbnails"
        "${BASE_DIR}/cache"
        "${BASE_DIR}/logs"
        "${BASE_DIR}/public/errors"
        "/var/www/.postgresql"
    )
    
    for dir in "${directories[@]}"; do
        if [ ! -d "$dir" ]; then
            echo "Creating directory: $dir"
            mkdir -p "$dir"
        fi
        chown -R www-data:www-data "$dir"
        chmod -R 775 "$dir"
    done
}

# Функция для настройки SSL
setup_ssl() {
    echo "Setting up SSL..."
    cert_dir="/var/www/.postgresql"
    cert_file="${cert_dir}/root.crt"
    
    if [ -f "/etc/ssl/certs/ca-certificates.crt" ]; then
        cp /etc/ssl/certs/ca-certificates.crt "$cert_file"
        chown www-data:www-data "$cert_file"
        chmod 644 "$cert_file"
        echo "SSL certificate installed"
    else
        echo "Warning: SSL certificate not found"
    fi
}

# Функция для настройки PHP
setup_php() {
    echo "Setting up PHP configuration..."
    
    # Создаем файл конфигурации PHP
    cat > /usr/local/etc/php/conf.d/custom.ini << EOF
upload_max_filesize = 10M
post_max_size = 10M
memory_limit = 256M
max_execution_time = 60
date.timezone = Europe/Moscow
display_errors = Off
log_errors = On
error_log = /var/www/html/logs/php_errors.log
EOF

    # Проверяем конфигурацию PHP
    php -v
    php -m
}

# Функция для настройки Apache
setup_apache() {
    echo "Setting up Apache configuration..."
    
    # Включаем необходимые модули
    a2enmod rewrite headers ssl
    
    # Проверяем конфигурацию Apache
    apache2ctl configtest
}

# Основной процесс
echo "Starting initialization..."

# Настраиваем директории
setup_directories

# Настраиваем SSL
setup_ssl

# Настраиваем PHP
setup_php

# Настраиваем Apache
setup_apache

# Ждем доступности базы данных
wait_for_db || exit 1

# Инициализируем базу данных
echo "Initializing database..."
php scripts/init_render_db.php

# Проверяем развертывание
echo "Running deployment checks..."
php scripts/check_deploy.php

if [ $? -ne 0 ]; then
    echo "Deployment check failed"
    exit 1
fi

echo "Initialization completed successfully"

# Запускаем Apache
echo "Starting Apache..."
exec apache2-foreground 
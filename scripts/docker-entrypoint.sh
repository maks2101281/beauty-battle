#!/bin/bash
set -e

# Функция для проверки доступности базы данных
wait_for_db() {
    echo "Waiting for database..."
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

# Функция для проверки директорий и прав
check_directories() {
    echo "Checking directories..."
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
            chown www-data:www-data "$dir"
            chmod 777 "$dir"
        fi
        
        if [ ! -w "$dir" ]; then
            echo "Warning: Directory $dir is not writable"
            chmod 777 "$dir"
        fi
    done
}

# Функция для проверки файлов ошибок
check_error_pages() {
    echo "Checking error pages..."
    error_pages=(
        "public/errors/404.html"
        "public/errors/500.html"
        "public/errors/403.html"
    )
    
    for page in "${error_pages[@]}"; do
        if [ ! -f "$page" ]; then
            echo "Error: $page not found"
            return 1
        fi
    done
}

# Функция для проверки конфигурации PHP
check_php_config() {
    echo "Checking PHP configuration..."
    required_extensions=(
        "pdo"
        "pdo_pgsql"
        "gd"
    )
    
    for ext in "${required_extensions[@]}"; do
        if ! php -m | grep -q "^$ext$"; then
            echo "Error: Required PHP extension $ext is not installed"
            return 1
        fi
    done
}

# Основной процесс
echo "Starting initialization..."

# Проверяем директории
check_directories

# Проверяем страницы ошибок
check_error_pages || exit 1

# Проверяем конфигурацию PHP
check_php_config || exit 1

# Ждем доступности базы данных
wait_for_db || exit 1

# Инициализируем базу данных
echo "Initializing database..."
php scripts/init_render_db.php

# Проверяем успешность инициализации
if [ $? -ne 0 ]; then
    echo "Database initialization failed"
    exit 1
fi

# Устанавливаем правильные права для Apache
chown -R www-data:www-data /var/www/html
find /var/www/html -type d -exec chmod 755 {} \;
find /var/www/html -type f -exec chmod 644 {} \;
chmod -R 777 /var/www/html/public/uploads
chmod -R 777 /var/www/html/cache
chmod -R 777 /var/www/html/logs

echo "Initialization completed successfully"

# Запускаем Apache
exec apache2-foreground 
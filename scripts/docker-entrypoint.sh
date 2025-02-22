#!/bin/bash
set -e

# Функция логирования
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

log "Starting Beauty Battle initialization..."

# Проверка переменных окружения
log "Checking environment variables..."
required_vars=(
    "DB_HOST"
    "DB_NAME"
    "DB_USER"
    "DB_PASSWORD"
    "APP_URL"
)

for var in "${required_vars[@]}"; do
    if [ -z "${!var}" ]; then
        log "Error: $var is not set"
        exit 1
    fi
    log "$var is set"
done
log "Environment variables OK"

# Создание необходимых директорий
log "Creating required directories..."
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
        log "Creating directory: $dir"
        mkdir -p "$dir"
        log "Directory $dir created"
    fi
    chown -R www-data:www-data "$dir"
    chmod -R 777 "$dir"
    log "Permissions set for $dir"
done
log "Directories setup completed"

# Проверка SSL сертификатов
log "Checking SSL certificates..."
cert_dir="/var/www/.postgresql"
cert_file="${cert_dir}/root.crt"

if [ ! -d "$cert_dir" ]; then
    log "Creating PostgreSQL certificate directory"
    mkdir -p "$cert_dir"
fi

if [ -f "/etc/ssl/certs/ca-certificates.crt" ]; then
    log "Copying SSL certificate"
    cp /etc/ssl/certs/ca-certificates.crt "$cert_file"
    chown www-data:www-data "$cert_file"
    chmod 644 "$cert_file"
    log "SSL certificate installed"
else
    log "Warning: SSL certificate not found"
fi

# Проверка подключения к базе данных
log "Checking database connection..."
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
        log "Database connection successful"
        break
    fi
    
    tries=$((tries + 1))
    log "Database connection attempt $tries of $max_tries"
    sleep 2
done

if [ $tries -eq $max_tries ]; then
    log "Error: Could not connect to database"
    exit 1
fi

# Проверка Apache
log "Checking Apache configuration..."
if apache2ctl configtest; then
    log "Apache configuration OK"
else
    log "Warning: Apache configuration test failed, but continuing"
fi

# Проверка PHP
log "Checking PHP configuration..."
php -v
php -m

# Проверка прав доступа
log "Checking file permissions..."
chown -R www-data:www-data /var/www/html
find /var/www/html -type d -exec chmod 755 {} \;
find /var/www/html -type f -exec chmod 644 {} \;
chmod -R 777 /var/www/html/public/uploads /var/www/html/cache /var/www/html/logs
chmod +x /var/www/html/scripts/*
log "File permissions set"

# Инициализация базы данных
log "Initializing database..."
if php scripts/init_render_db.php; then
    log "Database initialization completed"
else
    log "Warning: Database initialization failed"
fi

# Очистка кэша
log "Clearing cache..."
rm -rf /var/www/html/cache/*
log "Cache cleared"

# Проверка здоровья приложения
log "Running health check..."
if curl -f "http://localhost:8080/health.php" || curl -f "http://127.0.0.1:8080/health.php"; then
    log "Health check passed"
else
    log "Warning: Health check failed, but continuing"
fi

log "Initialization completed successfully"

# Запуск Apache
log "Starting Apache..."
exec apache2-foreground 
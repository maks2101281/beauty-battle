FROM php:8.1-apache

# Установка расширений PHP и зависимостей
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_pgsql \
    pgsql \
    gd

# Установка composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Копирование файлов проекта
COPY . /var/www/html/

# Создание необходимых директорий
RUN mkdir -p /var/www/html/public/uploads/photos \
    && mkdir -p /var/www/html/public/uploads/videos \
    && mkdir -p /var/www/html/public/uploads/thumbnails \
    && mkdir -p /var/www/html/cache \
    && mkdir -p /var/www/html/logs \
    && mkdir -p /var/www/.postgresql

# Установка прав доступа
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/public/uploads \
    && chmod -R 777 /var/www/html/cache \
    && chmod -R 777 /var/www/html/logs \
    && chmod -R 777 /var/www/.postgresql

# Создание .env файла из переменных окружения
RUN echo "DB_HOST=\${DB_HOST}\n\
DB_NAME=\${DB_NAME}\n\
DB_USER=\${DB_USER}\n\
DB_PASSWORD=\${DB_PASSWORD}\n\
TELEGRAM_BOT_TOKEN=\${TELEGRAM_BOT_TOKEN}\n\
TELEGRAM_BOT_USERNAME=\${TELEGRAM_BOT_USERNAME}\n\
APP_URL=\${APP_URL}\n\
APP_ENV=production\n\
APP_DEBUG=false\n\
UPLOAD_MAX_SIZE=2097152\n\
IMAGE_QUALITY=70\n\
SCRIPT_TIMEOUT=30\n\
SESSION_LIFETIME=120\n\
CSRF_TOKEN_LIFETIME=60\n\
CACHE_ENABLED=true\n\
CACHE_LIFETIME=3600" > /var/www/html/.env

# Установка зависимостей composer
RUN composer install --no-dev --optimize-autoloader

# Настройка Apache
COPY apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite headers

# Копирование SSL сертификата
RUN cp /etc/ssl/certs/ca-certificates.crt /var/www/.postgresql/root.crt

# Переменные окружения
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

# Порт
EXPOSE \${PORT}

# Запуск
CMD ["/var/www/html/scripts/render_start.sh"] 
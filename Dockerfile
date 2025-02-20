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

# Создание рабочей директории
WORKDIR /var/www/html

# Копирование только composer.json
COPY composer.json .

# Установка зависимостей composer
RUN composer install --no-dev --optimize-autoloader

# Копирование остальных файлов проекта
COPY . .

# Создание необходимых директорий
RUN mkdir -p public/uploads/photos \
    && mkdir -p public/uploads/videos \
    && mkdir -p public/uploads/thumbnails \
    && mkdir -p cache \
    && mkdir -p logs \
    && mkdir -p /var/www/.postgresql

# Установка прав доступа
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 public/uploads \
    && chmod -R 777 cache \
    && chmod -R 777 logs \
    && chmod -R 777 /var/www/.postgresql \
    && chmod +x scripts/render_start.sh

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
CACHE_LIFETIME=3600" > .env

# Копирование конфигурации Apache
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Включение модулей Apache
RUN a2enmod rewrite headers

# Копирование SSL сертификата
RUN cp /etc/ssl/certs/ca-certificates.crt /var/www/.postgresql/root.crt

# Настройка Apache
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Порт по умолчанию
ENV PORT=8080
EXPOSE 8080

# Запуск
CMD ["apache2-foreground"] 
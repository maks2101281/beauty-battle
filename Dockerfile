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
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql pgsql gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Создание рабочей директории и настройка прав
WORKDIR /var/www/html
RUN mkdir -p public/uploads/{photos,videos,thumbnails} cache logs /var/www/.postgresql \
    && chown -R www-data:www-data . \
    && chmod -R 755 . \
    && chmod -R 777 public/uploads cache logs /var/www/.postgresql

# Копирование файлов проекта
COPY --chown=www-data:www-data . .

# Создание .env файла
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

# Настройка Apache
COPY apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite headers \
    && cp /etc/ssl/certs/ca-certificates.crt /var/www/.postgresql/root.crt

# Настройка DocumentRoot
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

# Инициализация базы данных и настройка прав
RUN chmod +x scripts/init_render_db.php \
    && php scripts/init_render_db.php

ENV PORT=8080
EXPOSE 8080

CMD ["apache2-foreground"] 
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

# Настройка Apache
COPY apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite headers

# Создание рабочей директории и настройка прав
WORKDIR /var/www/html

# Создание директорий
RUN mkdir -p public/uploads/{photos,videos,thumbnails} \
    cache \
    logs \
    /var/www/.postgresql

# Копирование файлов проекта
COPY --chown=www-data:www-data . .

# Настройка прав доступа
RUN chown -R www-data:www-data . \
    && chmod -R 755 . \
    && chmod -R 777 public/uploads cache logs /var/www/.postgresql \
    && chmod +x scripts/* \
    && cp /etc/ssl/certs/ca-certificates.crt /var/www/.postgresql/root.crt

# Создание .env файла
RUN echo "DB_HOST=${DB_HOST}\n\
DB_NAME=${DB_NAME}\n\
DB_USER=${DB_USER}\n\
DB_PASSWORD=${DB_PASSWORD}\n\
TELEGRAM_BOT_TOKEN=${TELEGRAM_BOT_TOKEN}\n\
TELEGRAM_BOT_USERNAME=${TELEGRAM_BOT_USERNAME}\n\
APP_URL=${APP_URL}\n\
APP_ENV=production\n\
APP_DEBUG=false\n\
UPLOAD_MAX_SIZE=2097152\n\
IMAGE_QUALITY=70\n\
SCRIPT_TIMEOUT=30\n\
SESSION_LIFETIME=120\n\
CSRF_TOKEN_LIFETIME=60\n\
CACHE_ENABLED=true\n\
CACHE_LIFETIME=3600" > .env

# Настройка DocumentRoot
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -i 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#' /etc/apache2/sites-available/000-default.conf

# Проверка и создание необходимых директорий
RUN for dir in public/uploads/{photos,videos,thumbnails} cache logs public/errors; do \
        if [ ! -d "$dir" ]; then \
            mkdir -p "$dir" && \
            chown www-data:www-data "$dir" && \
            chmod 777 "$dir"; \
        fi \
    done

# Проверка наличия файлов ошибок
RUN for error in 404 500 403; do \
        if [ ! -f "public/errors/${error}.html" ]; then \
            echo "Error page ${error} not found" >&2; \
            exit 1; \
        fi \
    done

# Настройка PHP
RUN echo "session.save_handler = files\n\
session.save_path = /var/www/html/cache\n\
session.gc_maxlifetime = 3600\n\
session.cookie_lifetime = 3600\n\
session.cookie_secure = On\n\
session.cookie_httponly = On\n\
session.use_strict_mode = On" > /usr/local/etc/php/conf.d/session.ini

ENV PORT=8080
EXPOSE 8080

# Запуск инициализации и Apache
CMD ["sh", "-c", "php scripts/init_render_db.php && apache2-foreground"] 
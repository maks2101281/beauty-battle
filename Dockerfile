FROM php:8.1-apache

# Установка системных зависимостей
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    libexif-dev \
    zip \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Настройка и установка PHP расширений
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_pgsql \
    gd \
    exif

# Включаем необходимые модули Apache
RUN a2enmod rewrite headers ssl

# Настройка PHP
RUN echo "upload_max_filesize = 10M\n\
post_max_size = 10M\n\
memory_limit = 256M\n\
max_execution_time = 60\n\
date.timezone = Europe/Moscow" > /usr/local/etc/php/conf.d/custom.ini

# Создание необходимых директорий
RUN mkdir -p /var/www/html/public/uploads/{photos,videos,thumbnails} \
    /var/www/html/cache \
    /var/www/html/logs \
    /var/www/.postgresql \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/public/uploads \
    /var/www/html/cache \
    /var/www/html/logs

# Копирование SSL сертификата
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY . /var/www/html/
RUN cp /etc/ssl/certs/ca-certificates.crt /var/www/.postgresql/root.crt \
    && chown -R www-data:www-data /var/www/.postgresql \
    && chmod 644 /var/www/.postgresql/root.crt

# Установка зависимостей через Composer
RUN composer install --no-dev --optimize-autoloader

# Настройка прав доступа
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && chmod -R 777 /var/www/html/public/uploads \
    /var/www/html/cache \
    /var/www/html/logs \
    && chmod +x /var/www/html/scripts/*

# Настройка Apache
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Проверка конфигурации
RUN apache2ctl configtest

# Копирование entrypoint скрипта
COPY scripts/docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

WORKDIR /var/www/html

ENV PORT=8080
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"] 
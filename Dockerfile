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
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql gd

# Настройка Apache и SSL
COPY apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite headers ssl && \
    mkdir -p /var/www/.postgresql && \
    cp /etc/ssl/certs/ca-certificates.crt /var/www/.postgresql/root.crt

# Создание рабочей директории
WORKDIR /var/www/html

# Создание необходимых директорий
RUN mkdir -p public/uploads/{photos,videos,thumbnails} \
    cache \
    logs \
    public/errors \
    && chown -R www-data:www-data . \
    && chmod -R 755 . \
    && chmod -R 777 public/uploads cache logs

# Копирование файлов проекта
COPY --chown=www-data:www-data . .

# Настройка PHP
RUN echo "upload_max_filesize = 10M\n\
post_max_size = 10M\n\
memory_limit = 256M\n\
max_execution_time = 60\n\
date.timezone = Europe/Moscow" > /usr/local/etc/php/conf.d/custom.ini

# Настройка DocumentRoot
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -i -e 's#/var/www/html#${APACHE_DOCUMENT_ROOT}#g' /etc/apache2/sites-available/*.conf

# Проверка конфигурации
RUN apache2ctl configtest

# Настройка прав доступа
RUN chown -R www-data:www-data . \
    && find . -type f -exec chmod 644 {} \; \
    && find . -type d -exec chmod 755 {} \; \
    && chmod -R 777 public/uploads cache logs \
    && chmod +x scripts/*

ENV PORT=8080
EXPOSE 8080

# Запуск через entrypoint скрипт
COPY scripts/docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"] 
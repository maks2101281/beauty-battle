FROM php:8.1-apache

# Установка расширений PHP
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_pgsql \
    gd

# Установка composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Копирование файлов проекта
COPY . /var/www/html/

# Установка зависимостей
RUN composer install --no-dev --optimize-autoloader

# Настройка Apache
RUN a2enmod rewrite
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Права доступа
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod +x /var/www/html/scripts/render_start.sh

# Переменные окружения
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

# Порт
EXPOSE $PORT

# Запуск
CMD ["/var/www/html/scripts/render_start.sh"] 
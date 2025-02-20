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

# Установка composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Создание рабочей директории и настройка прав
WORKDIR /var/www/html
RUN mkdir -p public/uploads/{photos,videos,thumbnails} cache logs /var/www/.postgresql \
    && chown -R www-data:www-data . \
    && chmod -R 755 . \
    && chmod -R 777 public/uploads cache logs /var/www/.postgresql

# Копирование файлов проекта
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY --chown=www-data:www-data . .

# Настройка Apache
COPY apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite headers \
    && cp /etc/ssl/certs/ca-certificates.crt /var/www/.postgresql/root.crt

ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

ENV PORT=8080
EXPOSE 8080

CMD ["apache2-foreground"] 
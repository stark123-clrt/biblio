FROM php:8.1-apache

# Installer les dépendances système + Composer
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install gd pdo pdo_mysql zip

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Activer le module Apache rewrite
RUN a2enmod rewrite

# Copier composer.json et installer les dépendances
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader

# Copier le code source
COPY . /var/www/html/

# Créer dossiers et permissions
RUN mkdir -p /var/www/html/temp/audio /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
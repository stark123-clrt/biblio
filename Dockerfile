FROM php:8.1-apache

# Installer les dépendances nécessaires pour le pilote MySQL
RUN apt-get update && apt-get install -y libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install gd pdo pdo_mysql

# Activer le module Apache rewrite (si besoin)
RUN a2enmod rewrite

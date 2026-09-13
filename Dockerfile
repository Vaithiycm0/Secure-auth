FROM php:8.2-apache

# System dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libssl-dev \
    pkg-config \
    unzip \
    git \
    openssl \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# Apache rewrite support
RUN a2enmod rewrite

# MongoDB PHP extension with TLS/OpenSSL support
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Application directory
WORKDIR /var/www/html

# Copy application
COPY . /var/www/html/

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
FROM php:8.2-apache

RUN apt-get update && apt-get install -y libzip-dev unzip git && docker-php-ext-install pdo_mysql zip && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

RUN pecl install mongodb && docker-php-ext-enable mongodb

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . /var/www/html/

RUN composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]

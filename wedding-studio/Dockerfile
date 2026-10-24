FROM php:8.4-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libonig-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 pdo_mysql mbstring zip gd intl exif bcmath opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
# Keep WebP support in a separate layer so an existing PHP base can be reused.
RUN apt-get update && apt-get install -y --no-install-recommends libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j2 gd \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY . .
RUN find . -type d -exec chmod 755 {} + \
    && find . -type f -exec chmod 644 {} + \
    && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache public/uploads \
    && chmod -R ug+rwX storage bootstrap/cache public/uploads
COPY deploy/apache.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/php-production.ini /usr/local/etc/php/conf.d/studio.ini
EXPOSE 80
CMD ["apache2-foreground"]

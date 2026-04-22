FROM php:8.2-fpm

# System dependencies
RUN apt-get update && apt-get install -y \
    git curl zip unzip libpq-dev libzip-dev libicu-dev libonig-dev \
    nodejs npm \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip intl mbstring bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files first for layer caching
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader

# Copy package files and build frontend
COPY package.json package-lock.json ./
RUN npm ci

# Copy the rest of the app
COPY . .

# Finish composer and build assets
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && npm run build

# Permissions
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]

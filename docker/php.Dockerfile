FROM php:8.2-fpm

# Install system stuff
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev zlib1g-dev libicu-dev g++ librdkafka-dev \
    && docker-php-ext-install pdo_mysql intl zip \
    && pecl install redis \
    && pecl install rdkafka \
    && docker-php-ext-enable redis rdkafka


# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Рабочая директория
WORKDIR /var/www/app

# Копируем composer.json и ставим зависимости
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-progress --no-interaction

# Копируем весь проект
COPY . .

CMD ["php-fpm"]

FROM php:8.2-fpm

# Install system stuff
RUN apt-get update && apt-get install -y \
    git unzip vim iputils-ping netcat-traditional \
    libzip-dev zlib1g-dev librdkafka-dev libssl-dev \
    && docker-php-ext-install pdo_mysql zip pcntl \
    && pecl install redis \
    && pecl install rdkafka \
    && docker-php-ext-enable redis rdkafka \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ARG UID=1000
RUN adduser --disabled-password --gecos "" --uid ${UID} appuser

WORKDIR /var/www/app
RUN chown -R appuser:appuser /var/www/app

# Copy composer.json and install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-progress --no-interaction --prefer-dist --no-dev --optimize-autoloader \
    && chown -R appuser:appuser /var/www/app

# Copy the rest of the project
COPY --chown=appuser:appuser . .

USER appuser

CMD ["php-fpm"]

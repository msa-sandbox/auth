# Use shared base image from Docker Hub
FROM alslob/msa-sandbox:php-base-8.2 AS base

# Set working directory
WORKDIR /var/www/app

# Copy composer files and install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-progress --no-interaction \
    --prefer-dist --no-dev --optimize-autoloader

# Copy the rest of the project
COPY --chown=appuser:appuser . .

# Switch to non-root user
USER appuser

# Default command
CMD ["php-fpm"]

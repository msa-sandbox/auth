# Use shared base image from Docker Hub
FROM alslob/msa-sandbox:php-base-8.2 AS base

# Set working directory
WORKDIR /origin

# Copy composer files first
COPY --chown=appuser:appuser ../composer.json ../composer.lock ./

# Install PHP dependencies
RUN composer install --no-scripts --no-progress --no-interaction \
    --prefer-dist --no-dev --optimize-autoloader

# Copy the rest of the project
COPY --chown=appuser:appuser ../ .

# Stay root (so initContainer can copy files)
USER root

# No application start — this is source image
CMD ["true"]

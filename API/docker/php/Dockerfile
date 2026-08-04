FROM php:8.4-fpm-alpine

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
        git \
        curl \
        unzip \
        icu-dev \
        libpq-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        intl \
        opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Create non-root user. vendor/ and var/ are named volumes: they must exist in the
# image with the right owner or Docker initialises them root-owned and composer fails.
RUN adduser -D -s /bin/sh symfony \
    && mkdir -p /var/www/html/vendor /var/www/html/var \
    && chown -R symfony:symfony /var/www/html

# Configure PHP-FPM to run as symfony user
RUN sed -i 's/www-data/symfony/g' /usr/local/etc/php-fpm.d/www.conf

USER symfony

EXPOSE 9000

CMD ["php-fpm"]

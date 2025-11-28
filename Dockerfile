# Multi-stage build for Sistema de Gestión Vial (SGV)
# PHP 8.2 with required extensions for Symfony 6.4

FROM php:8.2-fpm AS base

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libpq-dev \
    unzip \
    wget \
    xfonts-75dpi \
    xfonts-base \
    fontconfig \
    libjpeg62-turbo \
    libxrender1 \
    && rm -rf /var/lib/apt/lists/*

# Install wkhtmltopdf for PDF generation (Knp Snappy)
RUN wget https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-3/wkhtmltox_0.12.6.1-3.bookworm_amd64.deb \
    && apt-get update \
    && apt-get install -y ./wkhtmltox_0.12.6.1-3.bookworm_amd64.deb \
    && rm wkhtmltox_0.12.6.1-3.bookworm_amd64.deb \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure intl \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install \
    pdo_mysql \
    pdo_pgsql \
    pgsql \
    mysqli \
    intl \
    zip \
    gd \
    opcache \
    bcmath \
    exif \
    && docker-php-ext-enable \
    pdo_mysql \
    pdo_pgsql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure PHP-FPM for development
RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini \
    && echo "memory_limit = 512M" >> /usr/local/etc/php/php.ini \
    && echo "upload_max_filesize = 64M" >> /usr/local/etc/php/php.ini \
    && echo "post_max_size = 64M" >> /usr/local/etc/php/php.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/php.ini \
    && echo "date.timezone = America/Santiago" >> /usr/local/etc/php/php.ini

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY --chown=www-data:www-data . .

# Install Composer dependencies
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Create necessary directories
RUN mkdir -p var/cache var/log public/downloads public/uploads \
    && chown -R www-data:www-data var public/downloads public/uploads \
    && chmod -R 775 var public/downloads public/uploads

# Healthcheck
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD php-fpm -t || exit 1

EXPOSE 9000

CMD ["php-fpm"]

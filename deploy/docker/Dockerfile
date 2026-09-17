# ==============================================================================
# Laijau ERP — Production Multi-Stage Dockerfile
# Base: PHP 8.4-FPM Alpine / Debian
# ==============================================================================

# --- Stage 1: Build Frontend Assets ---
FROM node:20-alpine AS frontend-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci --prefer-offline --no-audit
COPY vite.config.js ./
COPY resources/ resources/
COPY public/ public/
RUN npm run build

# --- Stage 2: Composer Vendor Dependencies ---
FROM composer:2.7 AS vendor-builder
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    --ignore-platform-reqs

# --- Stage 3: Production Runtime ---
FROM php:8.4-fpm-bookworm AS production

LABEL maintainer="Laijau Systems <info@laijau.com>"
LABEL description="Production runtime for Laijau Enterprise ERP"

# System dependencies & build tools
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libpq-dev \
    libonig-dev \
    unzip \
    supervisor \
    cron \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Install & configure PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pdo_pgsql \
        bcmath \
        gd \
        intl \
        zip \
        pcntl \
        opcache

# Install Redis extension via PECL
RUN pecl install redis \
    && docker-php-ext-enable redis

# Set production working directory
WORKDIR /var/www/laijau

# Copy custom PHP and PHP-FPM configurations
COPY deploy/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-laijau.conf
COPY deploy/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Copy application source code
COPY . /var/www/laijau

# Copy vendors from Stage 2 and frontend assets from Stage 1
COPY --from=vendor-builder /app/vendor /var/www/laijau/vendor
COPY --from=frontend-builder /app/public/build /var/www/laijau/public/build

# Set permissions for web server
RUN chown -R www-data:www-data /var/www/laijau \
    && chmod -R 775 /var/www/laijau/storage \
    && chmod -R 775 /var/www/laijau/bootstrap/cache

# Expose PHP-FPM fastcgi port
EXPOSE 9000

USER www-data

# Healthcheck probe
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD SCRIPT_NAME=/up SCRIPT_FILENAME=/var/www/laijau/public/index.php REQUEST_METHOD=GET \
        cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1

CMD ["php-fpm", "-F"]

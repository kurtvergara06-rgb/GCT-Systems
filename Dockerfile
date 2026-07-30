# ----------------------------------------------------------
# STAGE 1: BUILD FRONTEND ASSETS
# ----------------------------------------------------------
FROM node:22-alpine AS frontend

WORKDIR /app

# Copy dependency files first for better Docker caching.
COPY package*.json ./

RUN npm ci

# Copy the full Laravel project.
COPY . .

# Build Vite production assets.
RUN npm run build


# ----------------------------------------------------------
# STAGE 2: LARAVEL APPLICATION
# ----------------------------------------------------------
FROM php:8.4-fpm-bookworm

WORKDIR /var/www/html

# Install Nginx, Supervisor, PHP dependencies, MySQL client,
# image-processing libraries, and required system packages.
RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    git \
    curl \
    unzip \
    passwd \
    default-mysql-client \
    ca-certificates \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        bcmath \
        intl \
        zip \
        gd \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Allow PHP-FPM's www-data user to read Render secret files.
# Render secret files are commonly accessible through group ID 1000.
RUN set -eux; \
    SECRET_GROUP="$(getent group 1000 | cut -d: -f1 || true)"; \
    if [ -z "$SECRET_GROUP" ]; then \
        groupadd --gid 1000 rendersecrets; \
        SECRET_GROUP="rendersecrets"; \
    fi; \
    usermod --append --groups "$SECRET_GROUP" www-data

# Copy Composer from the official Composer image.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy Laravel project files.
COPY . .

# Copy the Vite production build from the frontend stage.
COPY --from=frontend /app/public/build ./public/build

# Install Laravel production dependencies.
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# Create Laravel writable directories.
RUN mkdir -p \
    storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Copy server configuration.
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/start.sh /usr/local/bin/start.sh

# Make the startup script executable.
RUN chmod +x /usr/local/bin/start.sh

# Render web services normally use port 10000.
EXPOSE 10000

CMD ["/usr/local/bin/start.sh"]
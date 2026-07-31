# ----------------------------------------------------------
# STAGE 1: BUILD FRONTEND ASSETS
# ----------------------------------------------------------
FROM node:22-alpine AS frontend

WORKDIR /app

# Copy dependency files first for better Docker caching.
COPY package*.json ./

# Install frontend dependencies.
RUN npm ci

# Copy the full Laravel project.
COPY . .

# Receive Render environment variables as Docker build arguments.
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME

# Make the values available to Vite during npm run build.
ENV VITE_REVERB_APP_KEY="${VITE_REVERB_APP_KEY}"
ENV VITE_REVERB_HOST="${VITE_REVERB_HOST}"
ENV VITE_REVERB_PORT="${VITE_REVERB_PORT}"
ENV VITE_REVERB_SCHEME="${VITE_REVERB_SCHEME}"

# Stop the build when any required Reverb frontend value is missing.
RUN test -n "$VITE_REVERB_APP_KEY" \
    && test -n "$VITE_REVERB_HOST" \
    && test -n "$VITE_REVERB_PORT" \
    && test -n "$VITE_REVERB_SCHEME"

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
        pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Allow PHP-FPM's www-data user to read Render secret files.
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
COPY docker/start-reverb.sh /usr/local/bin/start-reverb.sh

# Make startup scripts executable.
RUN chmod +x \
    /usr/local/bin/start.sh \
    /usr/local/bin/start-reverb.sh

# Render web services normally use port 10000.
EXPOSE 10000

# Default command for the main Laravel web service.
# The separate Reverb service overrides this command with:
# /usr/local/bin/start-reverb.sh
CMD ["/usr/local/bin/start.sh"]
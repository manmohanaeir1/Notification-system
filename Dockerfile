FROM php:8.3-fpm

# Set working directory
WORKDIR /app

# Install system & build dependencies required for PECL extensions
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
    build-essential \
    autoconf \
    pkg-config \
    gcc \
    g++ \
    make \
    git \
    curl \
    zip \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    libpq-dev \
    zlib1g-dev \
    libzip-dev \
    libxml2-dev \
    libonig-dev \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
# Note: tokenizer, json, ctype are built-in PHP; don't need installation
RUN docker-php-ext-install \
    pdo_mysql \
    pdo_sqlite \
    bcmath \
    mbstring \
    xml \
    && pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . .

# Create required directories before composer runs
RUN mkdir -p bootstrap/cache storage/logs && chmod -R 777 bootstrap/cache storage

# Install PHP dependencies
RUN composer install --no-interaction --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /app

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    icu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    autoconf \
    g++ \
    make \
    linux-headers \
    git \
    unzip \
    curl

# Configure and install PHP extensions
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        intl \
        opcache \
        zip \
        gd \
        bcmath \
        mbstring \
        pcntl \
        sockets

# Redis extension via PECL
RUN pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear

# Xdebug (disabled by default, enabled via XDEBUG_MODE env)
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && rm -rf /tmp/pear

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin \
    --filename=composer \
    && composer --version

WORKDIR /var/www

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-custom.ini

# Create non-root user matching host UID (avoids permission issues)
RUN addgroup -g 1001 appgroup \
    && adduser -D -u 1001 -G appgroup appuser

USER appuser

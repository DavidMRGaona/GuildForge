FROM php:8.5.12-fpm-alpine3.24

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libwebp-dev \
    libzip-dev \
    zip \
    unzip \
    icu-dev \
    oniguruma-dev \
    libpq-dev \
    linux-headers \
    imagemagick \
    imagemagick-dev \
    $PHPIZE_DEPS

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl

# Install Redis, Imagick (better quality than GD for image processing) and Xdebug
# from their GitHub release tags, checked by hash, as in Dockerfile.prod:
# pecl.php.net is a single point of failure for every build
RUN docker-php-source extract \
    && mkdir -p /usr/src/php/ext/redis /usr/src/php/ext/imagick /usr/src/php/ext/xdebug \
    && curl -fsSL -o /tmp/redis.tar.gz https://github.com/phpredis/phpredis/archive/refs/tags/6.3.0.tar.gz \
    && curl -fsSL -o /tmp/imagick.tar.gz https://github.com/Imagick/imagick/archive/refs/tags/3.8.1.tar.gz \
    && curl -fsSL -o /tmp/xdebug.tar.gz https://github.com/xdebug/xdebug/archive/refs/tags/3.5.3.tar.gz \
    && echo "cb8f81df1a275599e4f8ddcfec7e1f65ed1953e6f5673649149fd680ebff4cad  /tmp/redis.tar.gz" | sha256sum -c - \
    && echo "b0e9279ddf6e75a8c6b4068e16daec0475427dbca7ce2e144e30a51a88aa5ddc  /tmp/imagick.tar.gz" | sha256sum -c - \
    && echo "954e56021668121ecc50b92d2ad1ce945f22ecf81ffc5bb5835219485b12ef5f  /tmp/xdebug.tar.gz" | sha256sum -c - \
    && tar -xzf /tmp/redis.tar.gz -C /usr/src/php/ext/redis --strip-components=1 \
    && tar -xzf /tmp/imagick.tar.gz -C /usr/src/php/ext/imagick --strip-components=1 \
    && tar -xzf /tmp/xdebug.tar.gz -C /usr/src/php/ext/xdebug --strip-components=1 \
    # Only PECL packaging fills in the imagick version; the tag leaves a placeholder
    && sed -i 's/@PACKAGE_VERSION@/3.8.1/' /usr/src/php/ext/imagick/php_imagick.h \
    && rm /tmp/redis.tar.gz /tmp/imagick.tar.gz /tmp/xdebug.tar.gz \
    && docker-php-ext-install -j$(nproc) redis imagick xdebug \
    && docker-php-source delete

# Configure Xdebug
RUN echo "xdebug.mode=develop,debug,coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=trigger" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Configure OPcache for development
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.revalidate_freq=0" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=192" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/opcache.ini

# OPcache is compiled into PHP 8.5 (no docker-php-ext-install, no zend_extension):
# fail the build if the CLI or the FPM binary comes without it
RUN php -m | grep -qx 'Zend OPcache' && php-fpm -m | grep -qx 'Zend OPcache'

# PHP configuration
RUN echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "upload_max_filesize=20M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size=20M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "max_execution_time=60" >> /usr/local/etc/php/conf.d/custom.ini

# Install Composer
COPY --from=composer:2.10 /usr/bin/composer /usr/bin/composer

# Create application directory
WORKDIR /var/www/html

# Create user for application
RUN addgroup -g 1000 -S www && adduser -u 1000 -S www -G www

# Set ownership
RUN chown -R www:www /var/www/html

USER www

EXPOSE 9000

CMD ["php-fpm"]
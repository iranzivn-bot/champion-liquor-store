FROM php:8.3-apache

# System dependencies
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev \
        zlib1g-dev \
        unzip \
        git \
        curl \
    && docker-php-ext-install pdo_mysql mysqli intl opcache \
    && docker-php-ext-enable opcache \
    && a2enmod rewrite headers \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# PHP/OPcache production tuning
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.enable_cli=0'; \
    echo 'expose_php=Off'; \
  } > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# Copy the application
COPY . .

# Runtime permissions
RUN mkdir -p storage/framework \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R a+rX /var/www/html \
    && chmod -R u+rwX storage uploads \
    && chmod +x docker-entrypoint.sh

EXPOSE 8080

CMD ["sh", "/var/www/html/docker-entrypoint.sh"]
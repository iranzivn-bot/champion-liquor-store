FROM php:8.3-apache

# System dependencies (MySQL server bundled into the container)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        mysql-server \
        libicu-dev \
        zlib1g-dev \
        unzip \
        git \
        curl \
        supervisor \
    && docker-php-ext-install pdo_mysql mysqli intl opcache \
    && docker-php-ext-enable opcache \
    && a2enmod rewrite headers \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# MySQL configuration — bind to localhost only, socket disabled
RUN mkdir -p /etc/mysql/conf.d \
    && printf '[mysqld]\nbind-address=127.0.0.1\nskip-networking=0\n' \
       > /etc/mysql/conf.d/docker.cnf

# MySQL data directory (ephemeral on Render free — acceptable for dev/staging)
RUN mkdir -p /var/run/mysqld && chown mysql:mysql /var/run/mysqld

# Supervisor: run both MySQL and Apache in the same container
COPY --chown=root:root docker-supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# PHP/OPcache production tuning
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=64'; \
    echo 'opcache.max_accelerated_files=5000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.enable_cli=0'; \
    echo 'expose_php=Off'; \
  } > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

COPY . .

RUN mkdir -p storage/framework uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R a+rX /var/www/html \
    && chmod -R u+rwX storage uploads \
    && chmod +x docker-entrypoint.sh

# Render free assigns $PORT at runtime (always 10000 on free)
# Apache's ports.conf will be rendered by the entrypoint at startup.

EXPOSE 80 3306

CMD ["sh", "/var/www/html/docker-entrypoint.sh"]
# MariaDB 11 base (pulls its own runtime, datadir, and mysql client).
# We layer Apache + PHP (Debian packages) and drive both processes with
# supervisord from a custom entrypoint — no dependency on the official
# mariadb entrypoint's CMD/initdb coupling.
FROM mariadb:11

# PHP + Apache (Debian packages)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        apache2 \
        libapache2-mod-php \
        php-mysql \
        php-mbstring \
        php-intl \
        php-zip \
        php-curl \
        php-gd \
        php-xml \
        php-opcache \
        php-bcmath \
        supervisor \
        libicu-dev \
        unzip \
        git \
        curl \
        wget \
    && a2enmod rewrite headers \
    && sed -i '/^Listen 80$/d' /etc/apache2/apache2.conf \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN PHP_VERSION=$(ls /etc/php | head -1) \
    && printf 'opcache.enable=1\nopcache.enable_cli=0\nexpose_php=Off\nmemory_limit=256M\ndate.timezone=UTC\n' \
       > /etc/php/$PHP_VERSION/apache2/conf.d/99-production.ini

WORKDIR /var/www/html

COPY . .

RUN mkdir -p storage/framework /run/mysqld \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R a+rX /var/www/html \
    && chmod -R u+rwX storage uploads \
    && chmod 755 /run/mysqld \
    && chmod +x docker-entrypoint.sh \
    && rm -rf docker-entrypoint-initdb.d

COPY docker-supervisord.conf /etc/supervisor/conf.d/00-main.conf

# Point supervisord at its config dir (Debian ships an empty supervisord.conf)
RUN printf '[supervisord]\nnodaemon=true\n[include]\nfiles = /etc/supervisor/conf.d/*.conf\n' > /etc/supervisor/supervisord.conf

EXPOSE 3306

CMD ["sh", "/var/www/html/docker-entrypoint.sh"]
# MariaDB 11 base (pulls its own runtime, datadir, and mysql client) with
# Apache + PHP (Debian packages). supervisord drives bootstrap → mariadbd and
# Apache; the custom entrypoint renders the Render web port immediately.
FROM mariadb:11

# PHP + Apache + flock (Debian packages)
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
        util-linux \
        libicu-dev \
        unzip \
        git \
        curl \
        wget \
    && a2enmod rewrite headers \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN PHP_VERSION=$(ls /etc/php | head -1) \
    && printf 'opcache.enable=1\nopcache.enable_cli=0\nexpose_php=Off\nmemory_limit=256M\ndate.timezone=UTC\n' \
       > /etc/php/$PHP_VERSION/apache2/conf.d/99-production.ini

WORKDIR /var/www/html

# Persist the bundled MariaDB datadir across container restarts on Render.
VOLUME /var/lib/mysql

COPY . .

RUN mkdir -p storage/framework storage/logs/errors storage/cache storage/sessions storage/exports storage/tmp uploads \
    && find uploads storage -type d -exec chmod u+rwX {} + \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R a+rX /var/www/html \
    && chmod 755 /run/mysqld \
    && chmod +x docker-entrypoint.sh docker-bootstrap.sh docker-mariadb-wait.sh \
    && rm -rf docker-entrypoint-initdb.d

COPY docker-supervisord.conf /etc/supervisor/conf.d/00-main.conf

# Point supervisord at its config dir (Debian ships an empty supervisord.conf)
RUN printf '[supervisord]\nnodaemon=true\n[include]\nfiles = /etc/supervisor/conf.d/*.conf\n' > /etc/supervisor/supervisord.conf

EXPOSE 3306

CMD ["sh", "/var/www/html/docker-entrypoint.sh"]
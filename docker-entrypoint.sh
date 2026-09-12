#!/bin/bash
# Bundled-MariaDB entrypoint for Render (single free service).
#
# Renders the Apache port config FIRST, then hands off to supervisord with no
# further work up front so the web port is bound on 0.0.0.0 within seconds.
# The DB bootstrap (docker-bootstrap.sh) and the real mariadbd are sequenced
# inside supervisord via a lock file — see docker-supervisord.conf.
set -e

PORT="${PORT:-8080}"
export DB_HOST="${DB_HOST:-127.0.0.1}"
export DB_PORT="${DB_PORT:-3306}"
export DB_NAME="${DB_NAME:-${MYSQL_DATABASE:-champion_store}}"
export DB_USER="${DB_USER:-${MYSQL_USER:-champion}}"
export DB_PASS="${DB_PASS:-${MYSQL_PASSWORD:-}}"

echo "[boot] configuring Apache to listen on ${PORT}..."
printf 'Listen %s\n' "$PORT" > /etc/apache2/ports.conf
printf 'ServerName champion-liquor-store\n' > /etc/apache2/conf-enabled/server-name.conf
sed -i "s|<VirtualHost \*:80>|<VirtualHost _default_:$PORT>|" /etc/apache2/sites-available/000-default.conf || true
sed -i "s|<VirtualHost \*:80>|<VirtualHost _default_:$PORT>|" /etc/apache2/sites-available/default-ssl.conf 2>/dev/null || true

echo "[boot] starting supervisord (bootstrap → mariadbd + apache)..."
exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
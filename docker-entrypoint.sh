#!/bin/bash
# Custom entrypoint: bootstraps MariaDB (if the datadir is empty), renders
# Apache's port, then hands off to supervisord which runs mariadbd + Apache.
set -e

PORT="${PORT:-8080}"
DB_NAME="${DB_NAME:-champion_store}"
DB_USER="${DB_USER:-champion}"
DB_PASS="${DB_PASS:-${MYSQL_PASSWORD:-}}"

echo "[boot] configuring Apache to listen on ${PORT} ..."
# Apache config files cannot expand $PORT at runtime, so we bake it in here.
printf 'Listen %s\nServerName %s\n' "$PORT" "champion-liquor-store" > /etc/apache2/ports.conf
sed -i "s|<VirtualHost \*:80>|<VirtualHost _default_:$PORT>|" /etc/apache2/sites-available/000-default.conf
sed -i "s|<VirtualHost \*:80>|<VirtualHost _default_:$PORT>|" /etc/apache2/sites-available/default-ssl.conf 2>/dev/null || true

# ─── MariaDB bootstrap ────────────────────────────────────────────────
if [ ! -d /var/lib/mysql/mysql ]; then
    echo "[boot] fresh datadir — initializing MariaDB..."
    install -d -o mysql -g mysql /var/lib/mysql
    mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/dev/null 2>&1 \
        || mariadb-install-db --user=mysql >/dev/null 2>&1
    NEEDS_USER=1
else
    NEEDS_USER=0
fi

echo "[boot] starting MariaDB (temporary, pre-supervisord)..."
/usr/sbin/mariadbd --user=mysql --bind-address=127.0.0.1 \
    --innodb-buffer-pool-size=48M --performance-schema=OFF &
MARIADB_PID=$!

i=0
until mariadb-admin ping -h 127.0.0.1 --silent 2>/dev/null; do
    i=$((i + 1))
    [ "$i" -ge 60 ] && { echo "[boot] mariadbd never came up"; exit 1; }
    sleep 1
done
echo "[boot] MariaDB ready."

if [ "$NEEDS_USER" = "1" ]; then
    echo "[boot] creating app database and user..."
    mariadb -h 127.0.0.1 -u root -e "
        CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`
          CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
        GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
        FLUSH PRIVILEGES;"
fi

# ─── Schema ───────────────────────────────────────────────────────────
if mariadb -h 127.0.0.1 -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -Nse "SHOW TABLES LIKE 'users'" 2>/dev/null | grep -q users; then
    echo "[boot] schema present — applying migrations."
    DB_PASS="$DB_PASS" php /var/www/html/database/deploy-db.php || echo "[boot] migrations reported errors (see above)."
else
    echo "[boot] importing full schema..."
    # The dump targets champion_store; our DB_NAME matches so it lands correctly.
    mariadb -h 127.0.0.1 -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" \
        < /var/www/html/database/champion_store.sql \
        || echo "[boot] schema import failed — will retry via deploy-db."
    DB_PASS="$DB_PASS" php /var/www/html/database/deploy-db.php || echo "[boot] deploy-db reported errors."
fi

# Gracefully stop the temporary server — supervisord starts its own.
kill "$MARIADB_PID" 2>/dev/null || true
wait "$MARIADB_PID" 2>/dev/null || true

mkdir -p storage/framework storage/sessions storage/cache storage/logs/errors storage/tmp

echo "[boot] starting supervisord (mariadbd + apache)..."
/usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf &
SUPERVISOR_PID=$!

# Give the real (supervisord-managed) mariadbd time to bind 127.0.0.1:3306 so
# the first web request never races — otherwise Render restarts us on a 503.
i=0
until mariadb-admin ping -h 127.0.0.1 --silent 2>/dev/null; do
    i=$((i + 1))
    if ! kill -0 "$SUPERVISOR_PID" 2>/dev/null; then
        echo "[boot] supervisord exited early."
        exit 1
    fi
    if [ "$i" -ge 90 ]; then
        echo "[boot] mariadbd still not ready after 90s."
        exit 1
    fi
    sleep 1
done
echo "[boot] services ready."

wait "$SUPERVISOR_PID"
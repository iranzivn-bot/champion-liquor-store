#!/bin/sh
set -e

PORT="${PORT:-8080}"

echo "[entrypoint] starting MySQL..."
/usr/sbin/mysqld --user=mysql &
MYSQL_PID=$!

echo "[entrypoint] waiting for MySQL to be ready..."
i=0
until mysqladmin ping -h 127.0.0.1 --silent 2>/dev/null; do
    i=$((i + 1))
    if [ "$i" -ge 60 ]; then
        echo "[entrypoint] MySQL not reachable after 60s"
        exit 1
    fi
    echo "[entrypoint] waiting... ($i)"
    sleep 1
done
echo "[entrypoint] MySQL is ready."

# Seed the database on first container boot (or when DB_NAME is missing).
# On Render free tier MySQL data is ephemeral — the schema is rebuilt on every
# fresh container, so the full seed + migration runs automatically.
DB_NAME="${DB_NAME:-champion_store}"
DB_USER="${DB_USER:-champion}"

if ! mysql -h 127.0.0.1 -u root -e "USE \`${DB_NAME}\`" 2>/dev/null; then
    echo "[entrypoint] database not found — seeding fresh install..."
    mysql -h 127.0.0.1 -u root -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -h 127.0.0.1 -u root -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY ''; GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%'; FLUSH PRIVILEGES;"
    php /var/www/html/database/deploy-db.php || echo "[entrypoint] schema setup reported errors (see above)."
else
    echo "[entrypoint] database exists — applying outstanding migrations..."
    DB_PASS="" php /var/www/html/database/deploy-db.php || echo "[entrypoint] migration reported errors (see above)."
fi

# Ensure writable runtime directories
mkdir -p storage/framework storage/sessions storage/cache storage/logs/errors storage/tmp

echo "[entrypoint] starting services (supervisord)..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
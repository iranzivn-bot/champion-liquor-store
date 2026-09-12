#!/bin/bash
# First-boot DB bootstrap for the bundled MariaDB, following the official
# mariadb-docker entrypoint pattern:
#   1. Initialize a fresh datadir with NORMAL root auth (no password) so the
#      app user can be created and the schema imported through the temp server.
#   2. Start a temporary mariadbd on the SOCKET (networking disabled), create
#      the app user/db + grant, import schema + run migrations via the socket.
#      This never hits the unix_socket-vs-TCP auth block plain `mariadb-admin
#      ping -h 127.0.0.1` hits on a fresh datadir.
#   3. Kill the temporary server; supervisord starts the real one next.
#
# Serialization: holds LOCKFILE while running. The mariadbd program in
# supervisord (docker-mariadb-wait.sh) blocks on the lock before starting the
# real server, so the temp server never races it on the same datadir.
set -e

DATADIR=/var/lib/mysql
SOCKET=/run/mysqld/mysqld.sock
LOCKFILE=/var/run/champion-store/bootstrap.lock

export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME="${DB_NAME:-${MYSQL_DATABASE:-champion_store}}"
export DB_USER="${DB_USER:-${MYSQL_USER:-champion}}"
export DB_PASS="${DB_PASS:-${MYSQL_PASSWORD:-}}"

mkdir -p /run/mysqld /var/run/champion-store
chown mysql:mysql /run/mysqld || true

exec 9>"$LOCKFILE"
flock -n 9 || { echo "[bootstrap] already bootstrapping — skipping."; exit 0; }

if [ ! -d "$DATADIR/mysql" ]; then
    echo "[bootstrap] initializing fresh datadir..."
    install -d -o mysql -g mysql "$DATADIR"
    # --auth-root-authentication-method=normal: root gets NO password (socket
    # still works); required so CREATE USER/GRANT work during bootstrap.
    mariadb-install-db --user=mysql --datadir="$DATADIR" \
        --auth-root-authentication-method=normal --skip-test-db \
        >/dev/null 2>&1 \
        || mariadb-install-db --user=mysql \
           --auth-root-authentication-method=normal --skip-test-db \
        >/dev/null 2>&1
    echo "[bootstrap] datadir initialized."
fi

echo "[bootstrap] starting temporary mariadbd (socket only)..."
/usr/sbin/mariadbd --user=mysql --datadir="$DATADIR" --socket="$SOCKET" \
    --skip-networking --innodb-buffer-pool-size=48M --performance-schema=OFF \
    >/tmp/mariadbd-bootstrap.log 2>&1 &
MARIADB_PID=$!

# Wait for the socket (root has no password under normal auth on a fresh dir).
i=0
until mariadb -uroot --socket="$SOCKET" -Nse "SELECT 1" >/dev/null 2>&1; do
    i=$((i + 1))
    if ! kill -0 "$MARIADB_PID" 2>/dev/null; then
        echo "[bootstrap] temporary mariadbd died."
        cat /tmp/mariadbd-bootstrap.log
        exit 1
    fi
    if [ "$i" -ge 120 ]; then
        echo "[bootstrap] temporary mariadbd never came up."
        cat /tmp/mariadbd-bootstrap.log
        exit 1
    fi
    sleep 1
done

echo "[bootstrap] creating app database + user..."
mariadb -uroot --socket="$SOCKET" <<MYSQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
MYSQL

echo "[bootstrap] importing full schema..."
mariadb -uroot --socket="$SOCKET" "${DB_NAME}" \
    < /var/www/html/database/champion_store.sql \
    || echo "[bootstrap] schema import failed (will retry via seed-bootstrap)."

echo "[bootstrap] applying migrations via socket..."
SEED_SOCKET="$SOCKET" php /var/www/html/database/seed-bootstrap.php \
    || echo "[bootstrap] migrations reported errors (see above)."

kill "$MARIADB_PID" 2>/dev/null || true
wait "$MARIADB_PID" 2>/dev/null || true
echo "[bootstrap] done."
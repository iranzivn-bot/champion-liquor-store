#!/bin/sh
set -e

PORT="${PORT:-8080}"

# Render Apache config to listen on the Render-provided PORT ($PORT is not
# available to Apache's plain text config parser, so we render it at runtime).
echo "[entrypoint] configuring Apache to listen on ${PORT}"
printf 'ServerName localhost\nListen %s\n' "$PORT" > /etc/apache2/ports.conf

echo "[entrypoint] environment: ${APP_ENV:-development}"

# Render the database schema/migrations (waits for the DB; idempotent).
if [ "${SKIP_MIGRATIONS:-0}" = "1" ]; then
    echo "[entrypoint] SKIP_MIGRATIONS=1 — skipping schema setup."
else
    echo "[entrypoint] configuring schema..."
    php /var/www/html/database/deploy-db.php || echo "[entrypoint] schema setup reported errors (see above)."
fi

echo "[entrypoint] starting Apache..."
exec apache2-foreground
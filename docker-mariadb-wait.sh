#!/bin/bash
# Starts the REAL mariadbd. Blocks until docker-bootstrap.sh releases the lock
# it holds while its temporary bootstrap server runs, so the two never race on
# the same datadir, then execs the real server into this process (supervisord
# keeps supervising it).
set -e

LOCKFILE=/var/run/champion-store/bootstrap.lock

wait_for_bootstrap() {
    # The bootstrap holds LOCKFILE with flock; loop until it is free.
    while [ -e "$LOCKFILE" ]; do
        if flock -n "$LOCKFILE" true 2>/dev/null; then
            # acquired: bootstrap has finished
            return 0
        fi
        sleep 1
    done
    return 0
}

wait_for_bootstrap

exec /usr/sbin/mariadbd --user=mysql --datadir=/var/lib/mysql \
    --bind-address=127.0.0.1 --port=3306 --socket=/run/mysqld/mysqld.sock \
    --innodb-buffer-pool-size=48M --performance-schema=OFF --skip-name-resolve
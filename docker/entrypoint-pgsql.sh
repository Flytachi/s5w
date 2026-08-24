PGDATA=/var/lib/postgresql/data
if [ -z "$DB_HOST" ]; then
    echo "[entrypoint] DB_HOST не задан → поднимаю встроенный PostgreSQL"
    DB_HOST=127.0.0.1
    DB_PORT="${DB_PORT:-5432}"
    DB_NAME="${DB_NAME:-s4w}"
    DB_USER="${DB_USER:-s4w}"
    DB_PASS="${DB_PASS:-s4w}"
    DB_SCHEMA="${DB_SCHEMA:-public}"
    export DB_HOST DB_PORT DB_NAME DB_USER DB_PASS DB_SCHEMA

    mkdir -p "$PGDATA" /run/postgresql
    chown -R postgres:postgres /var/lib/postgresql /run/postgresql

    FRESH=0
    if [ ! -s "$PGDATA/PG_VERSION" ]; then
        FRESH=1
        su-exec postgres initdb -D "$PGDATA" --auth-local=trust --auth-host=md5 -E UTF8 >/dev/null
        printf "listen_addresses = '127.0.0.1'\nport = %s\n" "$DB_PORT" >> "$PGDATA/postgresql.conf"
    fi

    # Стартуем daemonized (pg_ctl форкает) — postgres остаётся жив после exec runsvdir.
    su-exec postgres pg_ctl -D "$PGDATA" -w -t 60 -l "$PGDATA/startup.log" start

    if [ "$FRESH" = "1" ]; then
        su-exec postgres sh -c "psql -p $DB_PORT -tAc \"SELECT 1 FROM pg_roles WHERE rolname='$DB_USER'\" | grep -q 1" \
            || su-exec postgres psql -p "$DB_PORT" -c "CREATE ROLE \"$DB_USER\" LOGIN PASSWORD '$DB_PASS';"
        su-exec postgres sh -c "psql -p $DB_PORT -tAc \"SELECT 1 FROM pg_database WHERE datname='$DB_NAME'\" | grep -q 1" \
            || su-exec postgres psql -p "$DB_PORT" -c "CREATE DATABASE \"$DB_NAME\" OWNER \"$DB_USER\";"
        echo "[entrypoint] fresh local DB → ./call db migrate"
        su-exec winter sh -c "cd /var/www/html && ./call db migrate" || echo "[entrypoint] WARN: db migrate не прошёл — запусти вручную"
    fi
    echo "[entrypoint] local PostgreSQL ready (db=$DB_NAME user=$DB_USER port=$DB_PORT)"
else
    echo "[entrypoint] external DB: $DB_HOST:${DB_PORT:-5432}/${DB_NAME:-s4w}"
fi
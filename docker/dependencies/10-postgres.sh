#!/bin/sh
set -e

# PostgreSQL server — встроенная база на случай, когда DB_HOST не задан.
# Клиент (psql, pg_isready) нужен инициализации; contrib даёт pgcrypto и uuid-ossp.
# Мажорная версия совпадает с боевой: дамп из 17 в 16 не зальётся.
if command -v postgres >/dev/null 2>&1; then
    echo "postgresql already present — skip"
else
    apk add --no-cache postgresql17 postgresql17-client postgresql17-contrib
    install -d -o postgres -g postgres -m 700 /var/lib/postgresql/data
    install -d -o postgres -g postgres -m 775 /run/postgresql
fi

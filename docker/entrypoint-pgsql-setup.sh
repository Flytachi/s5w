#!/command/with-contenv sh
# Роль, база и миграции для встроенного PostgreSQL. Отдельный oneshot, а не хвост
# в pgsql/run: сервер там уходит в exec и после него ничего выполнить нельзя,
# а роль с базой заводятся только на живом сервере.

PGPORT="${DB_PORT:-5432}"
DB_NAME="${DB_NAME:-s5w}"
DB_USER="${DB_USER:-s5w}"
DB_PASS="${DB_PASS:-s5w}"

# s6 поднимает зависимость, но «процесс стартовал» и «сервер принимает соединения» —
# разные события, на холодном старте между ними секунда-другая.
i=0
until su-exec postgres pg_isready -q -h /run/postgresql -p "$PGPORT"; do
    i=$((i + 1))
    if [ "$i" -ge 60 ]; then
        echo "[pgsql] сервер не поднялся за 60 с — пропускаю инициализацию" >&2
        exit 1
    fi
    sleep 1
done

psql() { su-exec postgres env PGOPTIONS=-cclient_min_messages=warning \
    /usr/bin/psql -qtAX -h /run/postgresql -p "$PGPORT" -d postgres "$@"; }

if [ "$(psql -c "SELECT 1 FROM pg_roles WHERE rolname = '$DB_USER'")" != "1" ]; then
    psql -c "CREATE ROLE \"$DB_USER\" LOGIN PASSWORD '$DB_PASS'"
    echo "[pgsql] создана роль $DB_USER"
fi

if [ "$(psql -c "SELECT 1 FROM pg_database WHERE datname = '$DB_NAME'")" != "1" ]; then
    psql -c "CREATE DATABASE \"$DB_NAME\" OWNER \"$DB_USER\""
    echo "[pgsql] создана база $DB_NAME"
fi

# Миграция идёт каждый старт, а не только на свежем каталоге: схема живёт в коде,
# и после обновления образа таблицы должны догнать его без ручного шага.
if su-exec winter php /var/www/html/call db migrate; then
    echo "[pgsql] готово: $DB_NAME@127.0.0.1:$PGPORT (пользователь $DB_USER)"
else
    echo "[pgsql] ВНИМАНИЕ: 'call db migrate' не прошёл — приложение стартует на текущей схеме" >&2
fi

#!/bin/sh
# Собирает дерево сервисов s6 и отдаёт управление /init.
#
# Почему сборка здесь, а не в Dockerfile: набор сервисов зависит от окружения —
# при внешней базе встроенный PostgreSQL регистрировать не надо. Окружение
# контейнера видно только этому скрипту (он и есть PID 1 до exec), сервисы s6
# получают его отдельно, через with-contenv.

RC=/etc/s6-overlay/s6-rc.d
BUNDLE=/etc/s6-overlay/user-bundles.d/user

# ── Секреты: WINTER_KEY ───────────────────────────────────────────────
SECRETS_FILE=/var/www/html/storage/.runtime_secrets
gen_hex() { head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n'; }   # 64 hex

# env имеет приоритет над persisted-значениями.
_env_winter="$WINTER_KEY"
if [ -z "$WINTER_KEY" ]; then
    [ -f "$SECRETS_FILE" ] && . "$SECRETS_FILE" 2>/dev/null || true
fi
[ -n "$_env_winter" ] && WINTER_KEY="$_env_winter"

genflag=0
if [ -z "$WINTER_KEY" ]; then WINTER_KEY="$(gen_hex)"; genflag=1; echo "[entrypoint] WINTER_KEY auto-generated"; fi
export WINTER_KEY

# Сохраняем только если что-то сгенерили (секреты из env на диск не пишем).
if [ "$genflag" = "1" ]; then
    mkdir -p "$(dirname "$SECRETS_FILE")"
    { printf 'WINTER_KEY=%s\n' "$WINTER_KEY"; } > "$SECRETS_FILE"
    chmod 600 "$SECRETS_FILE" 2>/dev/null || true
fi

# ── Регистрация сервисов ──────────────────────────────────────────────
# cp, а не mv: контейнер переживает `docker restart`, и на втором старте
# исходника уже не было бы на месте.
longrun() {
    mkdir -p "$RC/$1/dependencies.d"
    echo longrun > "$RC/$1/type"
    cp "$2" "$RC/$1/run" && chmod +x "$RC/$1/run"
    touch "$RC/$1/dependencies.d/base" "$BUNDLE/contents.d/$1"
}

oneshot() {
    mkdir -p "$RC/$1/dependencies.d"
    echo oneshot > "$RC/$1/type"
    cp "$2" "$RC/$1/script" && chmod +x "$RC/$1/script"
    echo "$RC/$1/script" > "$RC/$1/up"
    touch "$RC/$1/dependencies.d/base" "$BUNDLE/contents.d/$1"
}

mkdir -p "$BUNDLE/contents.d"
echo bundle > "$BUNDLE/type"

# ── Встроенный PostgreSQL ─────────────────────────────────────────────
# Внешняя база узнаётся по DB_HOST. Он приходит либо из окружения, либо из .env,
# который приложение читает само — поэтому файл тоже приходится посмотреть:
# иначе контейнер с боевым DSN в .env молча поднял бы пустую локальную базу
# и увёл приложение на неё.
DB_HOST_EFFECTIVE="$DB_HOST"
if [ -z "$DB_HOST_EFFECTIVE" ] && [ -f /var/www/html/.env ]; then
    DB_HOST_EFFECTIVE=$(sed -n 's/^[[:space:]]*DB_HOST[[:space:]]*=[[:space:]]*//p' /var/www/html/.env \
        | tail -n 1 | tr -d '"'"'"' \r')
fi

longrun service /opt/winter/service.run

if [ -n "$DB_HOST_EFFECTIVE" ]; then
    echo "[entrypoint] внешняя база: $DB_HOST_EFFECTIVE — встроенный PostgreSQL не поднимаю"
else
    echo "[entrypoint] DB_HOST не задан — поднимаю встроенный PostgreSQL"

    # Экспорт до exec /init: s6 снимает окружение при старте и раздаёт его
    # сервисам через with-contenv, так что приложение увидит именно эти значения.
    export DB_HOST=127.0.0.1
    export DB_PORT="${DB_PORT:-5432}"
    export DB_NAME="${DB_NAME:-s5w}"
    export DB_USER="${DB_USER:-s5w}"
    export DB_PASS="${DB_PASS:-s5w}"
    export DB_SCHEMA="${DB_SCHEMA:-public}"

    longrun pgsql /opt/winter/pgsql.run
    oneshot pgsql-setup /opt/winter/pgsql-setup.up
    touch "$RC/pgsql-setup/dependencies.d/pgsql" \
          "$RC/service/dependencies.d/pgsql-setup"
fi

exec /init

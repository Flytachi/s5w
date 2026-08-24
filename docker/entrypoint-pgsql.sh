#!/command/with-contenv sh
# Встроенный PostgreSQL. Регистрируется entrypoint'ом только когда DB_HOST не задан
# ни в окружении, ни в .env — при внешней базе этого сервиса в образе просто нет.
#
# with-contenv в шебанге обязателен: s6 запускает сервисы с пустым окружением и
# кладёт переменные контейнера в /run/s6/container_environment.

PGDATA=/var/lib/postgresql/data
PGPORT="${DB_PORT:-5432}"

install -d -o postgres -g postgres -m 700 "$PGDATA"
install -d -o postgres -g postgres -m 775 /run/postgresql

if [ ! -s "$PGDATA/PG_VERSION" ]; then
    echo "[pgsql] пустой каталог — initdb"
    su-exec postgres initdb -D "$PGDATA" \
        --auth-local=trust --auth-host=scram-sha-256 -E UTF8 --locale=C >/dev/null
fi

# Параметры флагами, а не дописыванием в postgresql.conf: перезапуск контейнера
# иначе накапливал бы в конфиге повторяющиеся строки.
exec su-exec postgres postgres -D "$PGDATA" \
    -c listen_addresses=127.0.0.1 \
    -c port="$PGPORT" \
    -c unix_socket_directories=/run/postgresql

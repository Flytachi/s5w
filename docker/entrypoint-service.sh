#!/command/with-contenv sh
# Swoole entrypoint. Под s6 PID 1 — это s6-svscan, а мастер Swoole живёт прямым
# потомком s6-supervise: su-exec делает exec, поэтому SIGTERM от супервизора
# доходит до мастера и тот гасится штатно. Логи идут в stdout, `docker logs` их
# показывает без syslog-релея.
#
# with-contenv в шебанге обязателен: s6 запускает сервисы с пустым окружением,
# без него SERVER_PORT и DEV сюда не доедут.
PORT="${SERVER_PORT:-9090}"

# Opcache is toggled here (runtime, as root before su-exec), so switching dev/prod
# needs no rebuild. Idempotent: safe on a fresh or a restarted container.
OPCACHE_CONF=/usr/local/etc/php/conf.d/10-opcache.ini

if [ "${DEV:-false}" = "true" ]; then
    # Development: opcache off (mounted code always live) + DevWatcher hot-reload.
    rm -f "$OPCACHE_CONF"
    exec su-exec winter php /var/www/html/call run dev --port="$PORT"
else
    # Production: tuned opcache on.
    cp /opt/winter/php-opcache.ini "$OPCACHE_CONF"

    # Warm the class-list cache before the server exists. Without it the very first
    # boot walks the whole tree itself, `require_once`-ing every .php it meets — and
    # that happens in the master, which every worker is forked from. Measured on 304
    # files: 40 ms and 10 MB in the master without the warm-up, 12 ms and 8 MB with it.
    # The two megabytes are per worker, so the saving is not only at startup.
    #
    # A failed build is a broken application, not a slow one: `di build` exits non-zero
    # and names the file, so refusing to start turns a crash-looping container into one
    # clear line in `docker logs`. This script has no `set -e` on purpose — the check is
    # explicit so the reason is in the log, not just in the exit code.

    if ! su-exec winter php /var/www/html/call di build; then
        echo "entrypoint: 'call di build' failed — refusing to start" >&2
        exit 1
    fi

    exec su-exec winter php /var/www/html/call run --port="$PORT"
fi

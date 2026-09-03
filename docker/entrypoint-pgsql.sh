#!/command/with-contenv sh
# with-contenv is required: s6 starts services with an empty environment.

PGDATA=/var/lib/s5w/postgresql

# /run is empty on every boot; the data directory comes from the volume.
install -d -o postgres -g postgres -m 775 /run/postgresql

if [ ! -s "$PGDATA/PG_VERSION" ]; then
    echo "pgsql: empty data directory — running initdb"
    su-exec postgres initdb -D "$PGDATA" \
        --auth-local=trust --auth-host=scram-sha-256 -E UTF8 --locale=C >/dev/null
fi

# Settings as flags, not appended to postgresql.conf: a restart would otherwise
# keep adding duplicate lines to the config.
#
# log_min_messages keeps WARNING and above. It cannot hide the startup lines:
# in this setting LOG ranks above ERROR, so silencing it would silence errors
# too. The recurring noise is checkpoints, and that has its own switch.
exec su-exec postgres postgres -D "$PGDATA" \
    -c listen_addresses=127.0.0.1 \
    -c port=5432 \
    -c unix_socket_directories=/run/postgresql \
    -c log_min_messages=warning \
    -c log_checkpoints=off \
    -c log_connections=off \
    -c log_disconnections=off \
    -c log_line_prefix='%t [%p] '

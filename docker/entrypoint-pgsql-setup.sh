#!/command/with-contenv sh
# Role, database and migrations. A separate oneshot rather than a tail of
# pgsql/run: that script ends in exec, and a role cannot be created before the
# server accepts connections anyway.
#
# Values are hardcoded to match Main\Configuration\MainDbConfig — change both.

PGPORT=5432
DB_NAME=s5w
DB_USER=s5w
DB_PASS=s5w

i=0
until su-exec postgres pg_isready -q -h /run/postgresql -p "$PGPORT"; do
    i=$((i + 1))
    if [ "$i" -ge 60 ]; then
        echo "pgsql: server did not come up within 60s — skipping setup" >&2
        exit 1
    fi
    sleep 1
done

psql() { su-exec postgres env PGOPTIONS=-cclient_min_messages=warning \
    /usr/bin/psql -qtAX -h /run/postgresql -p "$PGPORT" -d postgres "$@"; }

if [ "$(psql -c "SELECT 1 FROM pg_roles WHERE rolname = '$DB_USER'")" != "1" ]; then
    psql -c "CREATE ROLE \"$DB_USER\" LOGIN PASSWORD '$DB_PASS'"
    echo "pgsql: role $DB_USER created"
fi

# Migrations only for a database that has just been created. `db migrate` builds
# CREATE statements from the entity declarations and never reads the live schema,
# so on an existing database it can only report "already exists" — every object
# as one ERROR line in the server log. It also cannot add a column to a table
# that is already there: after changing an entity, run it by hand and expect to
# write the ALTER yourself.
if [ "$(psql -c "SELECT 1 FROM pg_database WHERE datname = '$DB_NAME'")" != "1" ]; then
    psql -c "CREATE DATABASE \"$DB_NAME\" OWNER \"$DB_USER\""
    echo "pgsql: database $DB_NAME created"

    if ! su-exec winter php /var/www/html/call db migrate; then
        echo "pgsql: 'call db migrate' failed — the schema is incomplete" >&2
    fi
fi

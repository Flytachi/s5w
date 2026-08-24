#!/bin/sh
# Builds the s6 service tree, then hands over to /init.

RC=/etc/s6-overlay/s6-rc.d
BUNDLE=/etc/s6-overlay/user-bundles.d/user

# On the data volume, not in storage/: it is the only directory that survives a
# container recreation, and a regenerated key invalidates every issued session.
SECRETS_FILE=/var/lib/s5w/.runtime_secrets
gen_hex() { head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n'; }

_env_winter="$WINTER_KEY"
if [ -z "$WINTER_KEY" ]; then
    [ -f "$SECRETS_FILE" ] && . "$SECRETS_FILE" 2>/dev/null || true
fi
[ -n "$_env_winter" ] && WINTER_KEY="$_env_winter"

genflag=0
if [ -z "$WINTER_KEY" ]; then WINTER_KEY="$(gen_hex)"; genflag=1; echo "entrypoint: WINTER_KEY auto-generated"; fi
export WINTER_KEY

if [ "$genflag" = "1" ]; then
    mkdir -p "$(dirname "$SECRETS_FILE")"
    { printf 'WINTER_KEY=%s\n' "$WINTER_KEY"; } > "$SECRETS_FILE"
    chmod 600 "$SECRETS_FILE" 2>/dev/null || true
fi

# cp, not mv: the container survives `docker restart` and the source has to
# still be there on the second start.
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

longrun pgsql /opt/winter/pgsql.run
oneshot pgsql-setup /opt/winter/pgsql-setup.up
longrun service /opt/winter/service.run

# The application starts after the database answers and migrations are applied.
touch "$RC/pgsql-setup/dependencies.d/pgsql" \
      "$RC/service/dependencies.d/pgsql-setup"

exec /init

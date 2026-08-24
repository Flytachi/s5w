#!/bin/sh

# ── 1. Секреты: WINTER_KEY ────────────────────────────────────────────
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


# FINAL - service
mkdir -p /etc/s6-overlay/s6-rc.d/service \
    && echo "longrun" > /etc/s6-overlay/s6-rc.d/service/type

mv /entrypoint-service.sh /etc/s6-overlay/s6-rc.d/service/run
chmod +x /etc/s6-overlay/s6-rc.d/service/run


# run
mkdir -p /etc/s6-overlay/s6-rc.d/user/contents.d \
    && touch /etc/s6-overlay/s6-rc.d/user/contents.d/service

exec /init
#!/bin/sh
set -e

# PostgreSQL server. The client is needed by the init scripts, contrib provides
# pgcrypto and uuid-ossp.
#
# The major version is pinned to the pgdata volume: a newer server will not open
# an older data directory without pg_upgrade.
if command -v postgres >/dev/null 2>&1; then
    echo "postgresql already present — skip"
else
    apk add --no-cache postgresql18 postgresql18-client postgresql18-contrib
fi

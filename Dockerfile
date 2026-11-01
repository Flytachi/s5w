# syntax=docker/dockerfile:1
# =============================================================================
# s5w — on the Winter runtime image, with PostgreSQL and the application under s6.
# -----------------------------------------------------------------------------
#   - PHP extensions the image lacks → docker/dependencies/*.sh (filename order)
#   - dev vs prod                    → DEV in docker-compose.yml
# =============================================================================

# ─────────────────────────────────────────────────────────────────────────────
# vendor — composer install, no dev deps; composer never ships in the final image.
# ─────────────────────────────────────────────────────────────────────────────
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-plugins --no-interaction --optimize-autoloader --ignore-platform-reqs

# ─────────────────────────────────────────────────────────────────────────────
# frontend — the admin panel (Nuxt, SPA): static files the application serves on
# UI_PORT. Node stays in this stage; the image itself runs no JavaScript.
# ─────────────────────────────────────────────────────────────────────────────
FROM node:24-alpine AS frontend
WORKDIR /frontend
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
RUN npm run generate

FROM shinsenter/s6-overlay:v3.2.3.2 AS s6-source

# ─────────────────────────────────────────────────────────────────────────────
# final — the Winter runtime image: Swoole, pcntl, pgsql, redis, opcache, user winter.
# https://hub.docker.com/r/flytachi/winter
#
# The image runs as winter; this one runs as root, because s6 is PID 1 here and the
# database must run as postgres. Every service drops to its own user (su-exec).
# ─────────────────────────────────────────────────────────────────────────────
FROM flytachi/winter:8.5
USER root

COPY --from=s6-source / /

RUN apk add --no-cache su-exec procps

# Memory ceiling per worker. The image's 256M is not enough here: a chunk of an upload
# is megabytes in the heap and image processing as much again. Read the file before
# changing it: the box must hold worker_num × this value.
COPY docker/php-memory.ini /usr/local/etc/php/conf.d/20-memory.ini

# What the image lacks: the PostgreSQL server, gd (webp, avif) and exif. Placed BEFORE
# the app code copy so this rarely-changing layer stays cached across code edits.
#
# `|| exit 1` is what fails the build on a broken script. Without it the loop keeps
# going and RUN reports the status of the last iteration, so an image can come out
# green with the extension missing — found out at runtime instead.
COPY docker/dependencies/ /tmp/dependencies/
RUN --mount=type=cache,target=/var/cache/apk \
    set -eu; \
    ln -s /var/cache/apk /etc/apk/cache; \
    apk add --virtual .winter-build-deps $PHPIZE_DEPS linux-headers; \
    docker-php-source extract; \
    for f in /tmp/dependencies/*.sh; do \
        [ -e "$f" ] || continue; \
        echo "→ deps: $f"; sh "$f" || exit 1; \
    done; \
    docker-php-source delete; \
    apk del .winter-build-deps; \
    rm -f /etc/apk/cache; \
    rm -rf /tmp/dependencies /tmp/pear

# The files are winter's: the application runs as winter and writes .winter/.
COPY --from=vendor --chown=winter:winter /app/vendor ./vendor
COPY --chown=winter:winter . .
COPY --from=frontend --chown=winter:winter /frontend/.output/public ./frontend/.output/public

# Everything stateful lives under one volume, outside the code tree:
#   postgresql/  cluster, owned by postgres, 0700 as the server demands
#   buckets/     blobs, one directory per bucket
#   staging/     partial chunked uploads
# buckets and staging must share a filesystem: finishing an upload is a rename,
# and rename is only atomic within one.
RUN install -d -o winter -g winter -m 775 /var/lib/s5w /var/lib/s5w/buckets /var/lib/s5w/staging \
    && install -d -o postgres -g postgres -m 700 /var/lib/s5w/postgresql

# Service scripts are staged in /opt/winter and copied into the s6-rc tree by
# the entrypoint.
COPY docker/entrypoint.sh /entrypoint.sh
COPY docker/entrypoint-service.sh /opt/winter/service.run
COPY docker/entrypoint-pgsql.sh /opt/winter/pgsql.run
COPY docker/entrypoint-pgsql-setup.sh /opt/winter/pgsql-setup.up
RUN chmod +x /entrypoint.sh /opt/winter/service.run /opt/winter/pgsql.run /opt/winter/pgsql-setup.up

# How long s6 waits for the services to stop before it kills them. The application
# takes up to 35 s (its components' grace, then SIGKILL to what is left), PostgreSQL
# stops after it; s6's own default of 5 s would cut requests in flight and leave the
# database to crash recovery. docker stop must wait longer still: stop_grace_period.
ENV S6_SERVICES_GRACETIME=40000

# No EXPOSE on purpose. It publishes nothing — `ports:` in docker-compose.yml does that,
# and the port itself is decided there too. Baking a number in here would only add one
# that can disagree: `docker compose up` without `--build` reuses the existing image, so
# the recorded port would be whatever the last build happened to use. A metadata line
# that quietly lies is worse than no metadata line.
ENTRYPOINT ["/entrypoint.sh"]

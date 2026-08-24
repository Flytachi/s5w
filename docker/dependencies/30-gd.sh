#!/bin/sh
set -e

# GD (jpeg/png/webp/avif) + exif — обработка изображений при загрузке.
if php -m | grep -qi '^gd$'; then
    echo "gd already present — skip"
else
    apk add --no-cache libjpeg-turbo libpng libwebp libavif \
        && apk add --no-cache --virtual .gd-deps build-base \
            libjpeg-turbo-dev libpng-dev libwebp-dev libavif-dev \
        && docker-php-ext-configure gd --with-jpeg --with-webp --with-avif \
        && docker-php-ext-install -j"$(nproc)" gd \
        && apk del .gd-deps
fi

if php -m | grep -qi '^exif$'; then
    echo "exif already present — skip"
else
    docker-php-ext-install -j"$(nproc)" exif
fi

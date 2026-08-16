#!/bin/sh
set -e

# GD (jpeg/png/webp/avif) + exif — обработка изображений при загрузке.
# png/zlib тянутся самим расширением, остальные кодеки включаются явно:
# без --with-webp сборка проходит, а imagewebp() потом просто не существует.
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

# exif — отдельное расширение, зависимостей не имеет. Нужен, чтобы повернуть
# фото с телефона до ресайза: сам GD ориентацию не читает.
if php -m | grep -qi '^exif$'; then
    echo "exif already present — skip"
else
    docker-php-ext-install -j"$(nproc)" exif
fi

#!/usr/bin/env sh

set -e

PHP_MAJOR_VERSION=$(php -v | head -n 1 | awk '{print $2}' | cut -d. -f1)

# Install build dependencies.
pre_build() {
    apk add git g++ autoconf
}

# APCu: https://php.net/apcu
apcu() {
    cd /tmp

    git clone --depth 1 --branch v5.1.19 https://github.com/krakjoe/apcu && cd apcu

    phpize
    ./configure --with-php-config=/usr/local/bin/php-config

    make
    make install

    rm -rf /tmp/apcu
}

# Xdebug: https://xdebug.org
xdebug() {
    cd /tmp

    branch="2.9.8"
    if [ "${PHP_MAJOR_VERSION}" = "8" ]; then
        branch="3.0.3"
    fi

    git clone --depth 1 --branch ${branch} https://github.com/xdebug/xdebug && cd xdebug

    phpize
    ./configure --enable-xdebug

    make
    make install

    rm -rf /tmp/xdebug
}

# Remove build dependencies.
post_build() {
    apk del git g++ autoconf
}

pre_build
apcu
xdebug
post_build

#!/bin/sh

set -e

XDEBUG_CONFIG=/usr/local/etc/php/conf.d/00_xdebug
PHP_MAJOR_VERSION=$(php -v | head -n 1 | awk '{print $2}' | cut -d. -f1)

config="${XDEBUG_CONFIG}2"
if [[ ${PHP_MAJOR_VERSION} == "8" ]]; then
    config="${XDEBUG_CONFIG}3"
fi

enable()
{
    if [[ -f  ${config}.disable ]]; then
        mv ${config}.disable ${config}.ini;
        echo "Xdebug enabled";
    fi
}

disable()
{
    if [[ -f  "${config}.ini" ]]; then
        mv "${config}.ini" ${config}.disable;
        echo "Xdebug disabled";
    fi
}

case "$1" in
    (enable)
        enable
        exit 0
        ;;
    (disable)
        disable
        exit 0
        ;;
    (*)
        echo "Usage: $0 {enable|disable}"
        exit 2
        ;;
esac

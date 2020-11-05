#!/bin/sh

# Install dependencies.
composer install

# Start supervisord.
/usr/bin/supervisord -n -c /etc/supervisord.conf

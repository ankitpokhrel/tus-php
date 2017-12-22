#!/bin/bash

# Run composer install
composer self-update
composer install

# Run supervisord
/usr/bin/supervisord -n -c /etc/supervisord.conf

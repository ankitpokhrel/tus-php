#!/bin/bash

# Run composer
composer self-update
composer install

# Run supervisord
/usr/bin/supervisord -n -c /etc/supervisord.conf

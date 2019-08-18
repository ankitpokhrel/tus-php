#!/bin/sh
composer install

# This /sbin/runit-wrapper has been copied from the base image phpearth/php:7.2-nginx
# This is necessary to keep container in running state, otherwise container will
# exit immediately after it's built. We need to keep it running to handle
# incoming request. /sbin/runit-wrapper also handles SIGINT properly and
# let system handle those signals appropriately.
# see https://github.com/phpearth/docker-php/blob/master/docker/tags/nginx/sbin/runit-wrapper
/sbin/runit-wrapper

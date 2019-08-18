#!/bin/sh

echo 'Stop and remove tus-php containers ...'
docker stop tus-php-server tus-php-client tus-php-redis >> /dev/null
docker rm tus-php-server tus-php-client tus-php-redis >> /dev/null

echo 'Remove tus-php docker images ...'
docker rmi tus-php_tus-server tus-php_tus-client tus-php-base >> /dev/null

echo 'Remove and re-create uploads folder'
rm -rf $(pwd)/uploads
mkdir -p $(pwd)/uploads

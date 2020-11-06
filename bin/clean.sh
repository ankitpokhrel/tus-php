#!/bin/sh

ROOT_DIR=${ROOT_DIR-$(pwd)}

echo 'Stop and remove tus-php containers'
docker stop tus-php-server tus-php-client tus-php-redis 2> /dev/null
docker rm tus-php-server tus-php-client tus-php-redis 2> /dev/null

echo 'Remove tus-php docker network and images'
docker network rm tus-php-network 2> /dev/null
docker rmi tus-php_tus-server tus-php_tus-client tus-php-base 2> /dev/null

echo 'Remove and re-create uploads folder'
rm -rf ${ROOT_DIR}/uploads
mkdir -p ${ROOT_DIR}/uploads

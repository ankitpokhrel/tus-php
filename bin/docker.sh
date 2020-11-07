#!/bin/bash

PHP_VERSION=${PHP_VERSION-7}
ROOT_DIR=${ROOT_DIR-$(pwd)}
BASE_PATH="${ROOT_DIR}/docker/base/"
DOCKER_FILENAME="${BASE_PATH}Dockerfile"
COMPOSE_FILE="${ROOT_DIR}/docker/docker-compose.yml"
NETWORK_NAME="tus-php-network"

# Create a network.
(docker network ls -f name=${NETWORK_NAME} | grep ${NETWORK_NAME}) 2> /dev/null || \
    docker network create ${NETWORK_NAME}

# Build base image.
if [[ ${PHP_VERSION} == "8" ]]; then
    docker build -t tus-php-base -f ${DOCKER_FILENAME}.php8 ${BASE_PATH}
else
    docker build -t tus-php-base -f ${DOCKER_FILENAME} ${BASE_PATH}
fi

# Build client and server.
docker-compose -p tus-php -f ${COMPOSE_FILE} down
docker-compose -p tus-php -f ${COMPOSE_FILE} up --build --remove-orphans -d

docker exec tus-php-server mkdir -p uploads
docker exec tus-php-server chown www-data:root -R uploads

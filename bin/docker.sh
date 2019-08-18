#!/bin/bash

# Create network
NETWORK_NAME="tus-php-network"

(docker network ls -f name=${NETWORK_NAME} | grep ${NETWORK_NAME}) > /dev/null || \
    docker network create ${NETWORK_NAME}

# Build base image
BASE_PATH="docker/base/"
DOCKERFILENAME="${BASE_PATH}Dockerfile"

docker build \
  -t tus-php-base \
  -f ${DOCKERFILENAME} ${BASE_PATH}

# Build client and server
COMPOSE_FILE="docker/docker-compose.yml"

docker-compose -p tus-php -f ${COMPOSE_FILE} down
docker-compose -p tus-php -f ${COMPOSE_FILE} up --build --remove-orphans -d

docker exec tus-php-server mkdir -p uploads
docker exec tus-php-server chown www-data:root -R uploads

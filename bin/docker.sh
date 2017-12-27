#!/bin/bash

# Create network
NETWORK_NAME="tus-php-network"

(docker network ls -f name=${NETWORK_NAME} | grep ${NETWORK_NAME}) > /dev/null || \
    docker network create ${NETWORK_NAME}

# Build base image
BASE_PATH="docker/base/"

docker build -t tus-php-base \
    -f ${BASE_PATH}Dockerfile ${BASE_PATH}

# Build client and server
COMPOSE_FILE="docker/docker-compose.yml"

docker-compose -p tus-php -f ${COMPOSE_FILE} down
docker-compose -p tus-php -f ${COMPOSE_FILE} up --build --remove-orphans -d

# Create uploads dir
mkdir -p uploads

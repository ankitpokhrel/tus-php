#!/bin/bash

echo "Building base image..."
docker build -t tus-php-base \
    -f docker/base/Dockerfile docker/base/

echo "Building client image..."
docker build -t tus-php-client \
    -f docker/client/Dockerfile docker/client/

echo "Building server image..."
docker build -t tus-php-server \
    -f docker/server/Dockerfile docker/server/

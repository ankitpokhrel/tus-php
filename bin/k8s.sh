#!/bin/bash

# Set minikube docker env
eval $(minikube docker-env)

# Build images
bin/build.sh

# Build client and server image for k8s
docker build --no-cache -t tus-php-client-k8s -f Dockerfile.client.k8s .
docker build --no-cache -t tus-php-server-k8s -f Dockerfile.server.k8s .

# Delete old resources
kubectl delete -f k8s/ --recursive=true

# Create resources
kubectl create -f k8s/ --recursive=true

# Serve client in browser
minikube service tus-php-client

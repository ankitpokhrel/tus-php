#!/bin/bash

if [[ $# -eq 0 ]]; then
    printf "Options: uploads (List uploads), redis (Login to redis), clear-cache (Clear redis cache)"
    exit 0
fi

REDIS_CONTAINER=$(kubectl get pods | grep tus-php-redis | awk '{ print $1 }' | xargs)

case $1 in
    redis )
        kubectl exec -it ${REDIS_CONTAINER} -- redis-cli
        ;;

    clear-cache )
        kubectl exec -it ${REDIS_CONTAINER} -- redis-cli -n 0 flushall &> /dev/null
        printf "Cache cleared successfully."
        ;;

    uploads )
        minikube ssh "ls -la /tmp/tus-php/uploads"
esac

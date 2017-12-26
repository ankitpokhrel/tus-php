#!/bin/bash

OPTION="$1"
SSH="minikube ssh"

case $1 in
    redis )
        ${SSH} "ls -la /tmp/tus-php/redis"
        ;;

     clear-cache )
        ${SSH} "sudo rm -rf /tmp/tus-php/redis/dump.rdb"
        echo "Cache cleared successfully."
        ;;

    * )
        ${SSH} "ls -la /tmp/tus-php/uploads"
esac

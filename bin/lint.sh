#!/usr/bin/env sh

FIXER="php-cs-fixer"
bin=/usr/local/bin/${FIXER}

if ! command -v php-cs-fixer &>/dev/null; then
    wget https://cs.symfony.com/download/php-cs-fixer-v3.phar -O ${bin} && chmod a+x ${bin}
fi

if [[ "$1" == "dry" ]]; then
    ${FIXER} fix --dry-run --show-progress=dots --using-cache=no --verbose
else
    ${FIXER} fix
fi

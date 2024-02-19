<?php

return [

    /**
     * Redis connection parameters.
     */
    'redis' => [
        'host' => getenv('REDIS_HOST') !== false ? getenv('REDIS_HOST') : '127.0.0.1',
        'port' => getenv('REDIS_PORT') !== false ? getenv('REDIS_PORT') : '6379',
        'password' => getenv('REDIS_PASSWORD') !== false ? getenv('REDIS_PASSWORD') : null,
        'database' => getenv('REDIS_DB') !== false ? getenv('REDIS_DB') : 0,
    ],

    /**
     * File cache configs.
     */
    'file' => [
        'dir' => \TusPhp\Config::getCacheHome() . DIRECTORY_SEPARATOR . '.cache' . DIRECTORY_SEPARATOR,
        'name' => getenv('TUS_CACHE_FILE') !== false ? getenv('TUS_CACHE_FILE') : 'tus_php.server.cache',
    ],
];

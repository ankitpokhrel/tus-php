<?php

return [

    /**
     * Redis connection parameters.
     */
    'redis' => [
        'host' => '127.0.0.1',
        'port' => '6379',
        'database' => 5,
    ],

    /**
     * File cache configs.
     */
    'file' => [
        'dir' => '/var/www/.cache/',
        'name' => 'tus_php.cache',
        'meta' => [
            'name' => 'test'
        ]
    ],
];

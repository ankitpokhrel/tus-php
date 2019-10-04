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
        'dir' => \dirname(__DIR__) . DS . '.cache' . DS,
        'name' => 'tus_php.cache',
        'meta' => [
            'name' => 'test'
        ]
    ],
];

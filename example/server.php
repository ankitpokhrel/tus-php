<?php

require __DIR__ . '/../vendor/autoload.php';

$server = new \TusPhp\Tus\Server('redis');

$server->serve();

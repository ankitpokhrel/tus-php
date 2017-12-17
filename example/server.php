<?php

require '../vendor/autoload.php';

$server = new \TusPhp\Tus\Server('redis');

$server->serve();

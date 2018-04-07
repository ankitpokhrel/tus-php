<?php

require __DIR__ . '/../vendor/autoload.php';

$server   = new \TusPhp\Tus\Server('redis');
$response = $server->serve();

$response->send();

exit(0); // Exit from current PHP process.

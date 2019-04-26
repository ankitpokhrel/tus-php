<?php

/**
 * Sends HEAD request to figure out uploaded offset
 * if the file was uploaded partially previously.
 */

require __DIR__ . '/../../vendor/autoload.php';

use TusPhp\Exception\FileException;
use GuzzleHttp\Exception\ConnectException;

$client = new \TusPhp\Tus\Client('http://tus-php-server');

$uploadKey = uniqid('tus_file_');

try {
    $offset = $client->setKey($uploadKey)->getOffset();
    $status = false !== $offset ? 'resume' : 'new';
    $offset = false === $offset ? 0 : $offset;

    echo json_encode([
        'status' => $status,
        'bytes_uploaded' => $offset,
        'upload_key' => $uploadKey,
    ]);
} catch (ConnectException $e) {
    echo json_encode([
        'status' => 'error',
        'bytes_uploaded' => -1,
    ]);
} catch (FileException $e) {
    echo json_encode([
        'status' => 'resume',
        'bytes_uploaded' => 0,
        'upload_key' => '',
    ]);
}

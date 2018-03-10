<?php

/**
 * Sends HEAD request to figure out uploaded offset
 * if the file was uploaded partially previously.
 */

require '../vendor/autoload.php';

use TusPhp\Exception\FileException;
use TusPhp\Exception\ConnectionException;
use GuzzleHttp\Exception\ConnectException;

$client = new \TusPhp\Tus\Client('http://tus-php-server', 'redis');

if ( ! empty($_FILES)) {
    $status   = 'new';
    $fileMeta = $_FILES['tus_file'];
    $checksum = hash_file('md5', $fileMeta['tmp_name']);

    try {
        $offset = $client->setKey($checksum)->file($fileMeta['tmp_name'])->getOffset();

        if (false !== $offset) {
            $status = $offset >= $fileMeta['size'] ? 'uploaded' : 'resume';
        } else {
            $offset = 0;
        }

        echo json_encode([
            'status' => $status,
            'bytes_uploaded' => $offset,
            'checksum' => $checksum,
        ]);
    } catch (ConnectionException | ConnectException $e) {
        echo json_encode([
            'status' => 'error',
            'bytes_uploaded' => -1,
        ]);
    } catch (FileException $e) {
        echo json_encode([
            'status' => 'resume',
            'bytes_uploaded' => 0,
            'checksum' => '',
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'bytes_uploaded' => -1,
    ]);
}

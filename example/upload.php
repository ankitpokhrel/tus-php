<?php

/**
 * Uploads files in 5mb chunk.
 */

require '../vendor/autoload.php';

use TusPhp\Exception\FileException;
use TusPhp\Exception\ConnectionException;
use TusPhp\Exception\Exception as TusException;

$client = new \TusPhp\Tus\Client('http://tus-php-server', 'redis');

if ( ! empty($_FILES)) {
    $fileMeta = $_FILES['tus_file'];
    $checksum = hash_file('md5', $fileMeta['tmp_name']);

    try {
        $client->setKey($checksum)->file($fileMeta['tmp_name'], time() . '_' . $fileMeta['name']);

        $bytesUploaded = $client->upload(5000000); // Chunk of 5 mb

        echo json_encode([
            'status' => 'uploading',
            'bytes_uploaded' => $bytesUploaded,
            'checksum' => $checksum,
        ]);
    } catch (ConnectionException | FileException | TusException $e) {
        echo json_encode([
            'status' => 'error',
            'bytes_uploaded' => -1,
            'checksum' => '',
            'error' => $e->getMessage(),
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'bytes_uploaded' => -1,
        'error' => 'No input!',
    ]);
}

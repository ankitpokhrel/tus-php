<?php

/**
 * Uploads files in 5mb chunk.
 */

require __DIR__ . '/../../vendor/autoload.php';

use TusPhp\Exception\FileException;
use TusPhp\Exception\ConnectionException;
use TusPhp\Exception\Exception as TusException;

$client = new \TusPhp\Tus\Client('http://tus-php-server');

// Alert: Sanitize all inputs properly in production code
if ( ! empty($_FILES)) {
    $fileMeta  = $_FILES['tus_file'];
    $uploadKey = hash_file('md5', $fileMeta['tmp_name']);

    try {
        $client->setKey($uploadKey)->file($fileMeta['tmp_name'], time() . '_' . $fileMeta['name']);

        $bytesUploaded = $client->upload(5000000); // Chunk of 5 mb

        echo json_encode([
            'status' => 'uploading',
            'bytes_uploaded' => $bytesUploaded,
            'upload_key' => $uploadKey
        ]);
    } catch (ConnectionException | FileException | TusException $e) {
        echo json_encode([
            'status' => 'error',
            'bytes_uploaded' => -1,
            'upload_key' => '',
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

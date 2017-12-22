<?php

/**
 * Uploads files in 10mb chunk.
 */

require '../vendor/autoload.php';

$client = new \TusPhp\Tus\Client('http://tus-php-server', 'redis');

if ( ! empty($_FILES)) {
    $fileMeta = $_FILES['tus_file'];

    try {
        $client->file($fileMeta['tmp_name'], time() . '_' . $fileMeta['name']);

        $checksum = $client->getChecksum();

        $bytesUploaded = $client->upload(5000000); // Chunk of 5 mb

        echo json_encode([
            'status' => 'uploading',
            'bytes_uploaded' => $bytesUploaded,
            'checksum' => $checksum,
        ]);
    } catch (\TusPhp\Exception\ConnectionException $e) {
        echo json_encode([
            'status' => 'error',
            'bytes_uploaded' => -1,
            'checksum' => '',
            'error' => $e->getMessage(),
        ]);
    } catch (\TusPhp\Exception\FileException $e) {
        echo json_encode([
            'status' => 'uploading',
            'bytes_uploaded' => 0,
            'checksum' => '',
            'error' => $e->getMessage(),
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'bytes_uploaded' => -1,
    ]);
}

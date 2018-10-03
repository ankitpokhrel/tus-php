<?php

/**
 * Sends HEAD request to figure out uploaded offset
 * if the file was uploaded partially previously.
 */

require __DIR__ . '/../../vendor/autoload.php';

use TusPhp\Exception\FileException;
use GuzzleHttp\Exception\ConnectException;

$client = new \TusPhp\Tus\Client('http://tus-php-server');

// Alert: Sanitize all inputs properly in production code
if ( ! empty($_FILES)) {
    $status    = 'new';
    $fileMeta  = $_FILES['tus_file'];
    $uploadKey = hash_file('md5', $fileMeta['tmp_name']);

    try {
        $offset = $client->setKey($uploadKey)->file($fileMeta['tmp_name'])->getOffset();

        if (false !== $offset) {
            $status = $offset >= $fileMeta['size'] ? 'uploaded' : 'resume';
        } else {
            $offset = 0;
        }

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
} else {
    echo json_encode([
        'status' => 'error',
        'bytes_uploaded' => -1,
    ]);
}

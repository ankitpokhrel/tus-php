<?php

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
        $client->setKey($uploadKey)->file($fileMeta['tmp_name'], 'chunk_a');

        // Upload 50MB starting from 10MB
        $bytesUploaded = $client->seek(10000000)->upload(50000000);
        $partialKey1   = $client->getKey();
        $checksum      = $client->getChecksum();

        // Upload first 10MB
        $bytesUploaded = $client->setFileName('chunk_b')->seek(0)->upload(10000000);
        $partialKey2   = $client->getKey();

        // Upload remaining bytes starting from 60,000,000 bytes (60MB => 50000000 + 10000000)
        $bytesUploaded = $client->setFileName('chunk_c')->seek(60000000)->upload();
        $partialKey3   = $client->getKey();

        $client->setFileName($fileMeta['name'])->concat($uploadKey, $partialKey2, $partialKey1, $partialKey3);

        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?state=uploaded');
    } catch (ConnectionException | FileException | TusException $e) {
        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?state=failed');
    }
}

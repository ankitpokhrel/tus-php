<?php

require '../vendor/autoload.php';

use TusPhp\Exception\FileException;
use TusPhp\Exception\ConnectionException;
use TusPhp\Exception\Exception as TusException;

$client = new \TusPhp\Tus\Client('http://tus-php-server', 'redis');

if ( ! empty($_FILES)) {
    $fileMeta = $_FILES['tus_file'];

    try {
        $client->file($fileMeta['tmp_name'], 'chunk_a');
        $checksum = $client->getChecksum();

        // Upload  10000 bytes starting from 1000 byte
        $bytesUploaded = $client->seek(1000)->upload(10000);
        $partialChunk1 = $client->getChecksum();

        // Upload first 1000 bytes
        $bytesUploaded = $client->setFileName('chunk_b')->seek(0)->upload(1000);
        $partialChunk2 = $client->getChecksum();

        // Upload remaining bytes starting from 11000 bytes (10000 + 1000)
        $bytesUploaded = $client->setFileName('chunk_c')->seek(11000)->upload();
        $partialChunk3 = $client->getChecksum();

        $client->setFileName($fileMeta['name'])->concat($checksum, $partialChunk2, $partialChunk1, $partialChunk3);

        echo json_encode([
            'status' => 'uploading',
            'bytes_uploaded' => 0,
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
}
?>

<form action="" method="post" enctype="multipart/form-data">
    <input type="file" name="tus_file"/>
    <input type="submit" value="Upload"/>
</form>

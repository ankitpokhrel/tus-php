<?php

namespace TusPhp\Test\Fixtures;

class FileFixture
{
    /**
     * Make files and folder.
     *
     * @param string $path
     * @param array  $files
     *
     * @return void
     */
    public static function makeFilesAndFolder(string $path, array $files)
    {
        if ( ! file_exists($path)) {
            mkdir($path);
        }

        foreach ($files as $file) {
            $filePath = $file['file_path'];

            touch($filePath);

            file_put_contents($filePath, basename($filePath));
        }
    }
}

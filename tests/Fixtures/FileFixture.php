<?php

namespace TusPhp\Test\Fixtures;

class FileFixture
{
    /**
     * Make files and folder.
     *
     * @return null
     */
    public static function makeFilesAndFolder(string $path, array $files)
    {
        if ( ! file_exists($path)) {
            mkdir($path);
        }

        foreach ($files as $file) {
            touch($file);

            file_put_contents($file, basename($file));
        }
    }
}

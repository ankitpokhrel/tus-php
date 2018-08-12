<?php

namespace TusPhp\Events;

use TusPhp\File;

class Progress
{
    /**
     * @var \TusPhp\File
     */
    public $file;

    /**
     * @var int
     */
    public $fileSize;

    /**
     * @var int
     */
    public $offset;

    /**
     * @var int
     */
    public $bytesWritten;
    
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(File $file, int $fileSize, int $offset, int $bytesWritten)
    {
        $this->file = $file;
        $this->fileSize = $fileSize;
        $this->offset = $offset;
        $this->bytesWritten = $bytesWritten;
    }
}

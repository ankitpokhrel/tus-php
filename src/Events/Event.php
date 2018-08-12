<?php

namespace TusPhp\Events;

use TusPhp\File;
use TusPhp\Request;
use TusPhp\Response;

class Event
{

    /**
     * @var \TusPhp\File
     */
    public $file;

    /**
     * @var \TusPhp\Request
     */
    public $request;

    /**
     * @var \TusPhp\Response
     */
    public $response;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(File $file, Request $request, Response $response)
    {
        $this->file = $file;
        $this->request = $request;
        $this->response = $response;
    }
}

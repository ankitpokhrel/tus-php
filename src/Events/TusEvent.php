<?php

namespace TusPhp\Events;

use Symfony\Contracts\EventDispatcher\Event;
use TusPhp\File;
use TusPhp\Request;
use TusPhp\Response;

class TusEvent extends Event
{
    /** @var File */
    protected $file;

    /** @var Request */
    protected $request;

    /** @var Response */
    protected $response;

    /**
     * Get file.
     *
     * @return File
     */
    public function getFile() : File
    {
        return $this->file;
    }

    /**
     * Get request.
     *
     * @return Request
     */
    public function getRequest() : Request
    {
        return $this->request;
    }

    /**
     * Get response.
     *
     * @return Response
     */
    public function getResponse() : Response
    {
        return $this->response;
    }
}

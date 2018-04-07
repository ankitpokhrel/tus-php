<?php

namespace TusPhp;

use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class Response
{
    /** @var HttpResponse */
    protected $response;

    /** @var bool */
    protected $createOnly = true;

    /** @var array */
    protected $headers = [];

    /**
     * Response constructor.
     */
    public function __construct()
    {
        $this->response = new HttpResponse;
    }

    /**
     * Set create only.
     *
     * @param bool $state
     *
     * @return self
     */
    public function createOnly(bool $state) : self
    {
        $this->createOnly = $state;

        return $this;
    }

    /**
     * Set global headers.
     *
     * @param array $headers
     *
     * @return Response
     */
    public function setHeaders(array $headers) : self
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * Get global headers.
     *
     * @return array
     */
    public function getHeaders() : array
    {
        return $this->headers;
    }

    /**
     * Get create only.
     *
     * @return bool
     */
    public function getCreateOnly() : bool
    {
        return $this->createOnly;
    }

    /**
     * Create and send a response.
     *
     * @param mixed $content Response data.
     * @param int   $status  Http status code.
     * @param array $headers Headers.
     *
     * @return HttpResponse
     */
    public function send($content, int $status = HttpResponse::HTTP_OK, array $headers = []) : HttpResponse
    {
        $headers = array_merge($this->headers, $headers);

        $response = $this->response->create($content, $status, $headers);

        return $this->createOnly ? $response : $response->send();
    }

    /**
     * Create a new file download response.
     *
     * @param \SplFileInfo|string $file
     * @param string              $name
     * @param array               $headers
     * @param string|null         $disposition
     *
     * @return BinaryFileResponse
     */
    public function download(
        $file,
        string $name = null,
        array $headers = [],
        string $disposition = ResponseHeaderBag::DISPOSITION_ATTACHMENT
    ) : BinaryFileResponse {
        $response = new BinaryFileResponse($file, HttpResponse::HTTP_OK, $headers, true, $disposition);

        $response->prepare(HttpRequest::createFromGlobals());

        if ( ! is_null($name)) {
            $response = $response->setContentDisposition(
                $disposition,
                $name,
                iconv('UTF-8', 'ASCII//TRANSLIT', $name)
            );
        }

        return $this->createOnly ? $response : $response->send();
    }
}

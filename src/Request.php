<?php

namespace TusPhp;

use Illuminate\Http\Request as HttpRequest;

class Request
{
    /** @var HttpRequest */
    protected $request;

    /**
     * Request constructor.
     */
    public function __construct()
    {
        if (null === $this->request) {
            $this->request = HttpRequest::createFromGlobals();
        }
    }

    /**
     * Get http method from current request.
     *
     * @return string
     */
    public function method() : string
    {
        return $this->request->method();
    }

    /**
     * @return null|string
     */
    public function checksum()
    {
        return $this->request->segment(2);
    }

    /**
     * Supported http requests.
     *
     * @return array
     */
    public function allowedHttpVerbs() : array
    {
        return [
            HttpRequest::METHOD_GET,
            HttpRequest::METHOD_POST,
            HttpRequest::METHOD_PATCH,
            HttpRequest::METHOD_DELETE,
            HttpRequest::METHOD_HEAD,
            HttpRequest::METHOD_OPTIONS,
        ];
    }

    /**
     * Retrieve a header from the request.
     *
     * @param  string            $key
     * @param  string|array|null $default
     *
     * @return string|null
     */
    public function header(string $key, $default = null)
    {
        return $this->request->header($key, $default);
    }

    /**
     * Get the root URL for the request.
     *
     * @return string
     */
    public function url() : string
    {
        return $this->request->root();
    }

    /**
     * Extract base64 encoded filename from header.
     *
     * @return string|null
     */
    public function extractFileName()
    {
        $file = null;
        $meta = $this->header('Upload-Metadata');

        if (false !== strpos($meta, 'filename')) {
            list(, $file) = explode(' ', $meta) ?? null;
        }

        return $file ? base64_decode($file) : null;
    }

    /**
     * Get request.
     *
     * @return HttpRequest
     */
    public function getRequest()
    {
        return $this->request;
    }
}

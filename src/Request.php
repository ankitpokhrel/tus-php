<?php

namespace TusPhp;

use Symfony\Component\HttpFoundation\Request as HttpRequest;

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
        return $this->request->getMethod();
    }

    /**
     * Get the current path info for the request.
     *
     * @return string
     */
    public function path() : string
    {
        return $this->request->getPathInfo();
    }

    /**
     * Get upload key from url.
     *
     * @return string
     */
    public function key() : string
    {
        return basename($this->path());
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
        return $this->request->headers->get($key, $default);
    }

    /**
     * Get the root URL for the request.
     *
     * @return string
     */
    public function url() : string
    {
        return rtrim($this->request->getUriForPath('/'), '/');
    }

    /**
     * Extract metadata from header.
     *
     * @param string $key
     * @param string $value
     *
     * @return array
     */
    public function extractFromHeader(string $key, string $value) : array
    {
        $meta = $this->header($key);

        if (false !== strpos($meta, $value)) {
            $meta = trim(str_replace($value, '', $meta));

            return explode(' ', $meta) ?? [];
        }

        return [];
    }

    /**
     * Extract base64 encoded filename from header.
     *
     * @return string|null
     */
    public function extractFileName() : ?string
    {
        $meta = $this->header('Upload-Metadata');

        if (empty($meta)) {
            return null;
        }

        if (false !== strpos($meta, ',')) {
            $pieces = explode(',', $meta);

            list(/* $key */, $file) = explode(' ', $pieces[0]);
        } else {
            list(/* $key */, $file) = explode(' ', $meta);
        }

        return base64_decode($file);
    }

    /**
     * Extract partials from header.
     *
     * @return array
     */
    public function extractPartials() : array
    {
        return $this->extractFromHeader('Upload-Concat', 'final;');
    }

    /**
     * Check if this is a partial upload request.
     *
     * @return bool
     */
    public function isPartial() : bool
    {
        return $this->header('Upload-Concat') === 'partial';
    }

    /**
     * Check if this is a final concatenation request.
     *
     * @return bool
     */
    public function isFinal() : bool
    {
        return false !== strpos($this->header('Upload-Concat'), 'final;');
    }

    /**
     * Get request.
     *
     * @return HttpRequest
     */
    public function getRequest() : HttpRequest
    {
        return $this->request;
    }
}

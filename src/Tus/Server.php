<?php

namespace TusPhp\Tus;

use TusPhp\File;
use TusPhp\Request;
use TusPhp\Response;
use TusPhp\Cache\Cacheable;
use TusPhp\Exception\FileException;
use TusPhp\Exception\ConnectionException;
use TusPhp\Exception\OutOfRangeException;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class Server extends AbstractTus
{
    /** @const Tus Creation Extension */
    const TUS_EXTENSION_CREATION = 'creation';

    /** @const Tus Termination Extension */
    const TUS_EXTENSION_TERMINATION = 'termination';

    /** @const Tus Checksum Extension */
    const TUS_EXTENSION_CHECKSUM = 'checksum';

    /** @var Request */
    protected $request;

    /** @var Response */
    protected $response;

    /** @var string */
    protected $uploadDir;

    /**
     * TusServer constructor.
     *
     * @param Cacheable|string $cacheAdapter
     */
    public function __construct($cacheAdapter = 'file')
    {
        $this->request   = new Request;
        $this->response  = new Response;
        $this->uploadDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads';

        $this->setCache($cacheAdapter);
    }

    /**
     * Set upload dir.
     *
     * @param string $path
     *
     * @return void
     */
    public function setUploadDir(string $path)
    {
        $this->uploadDir = $path;
    }

    /**
     * Get upload dir.
     *
     * @return string
     */
    public function getUploadDir() : string
    {
        return $this->uploadDir;
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
     * Get request.
     *
     * @return Response
     */
    public function getResponse() : Response
    {
        return $this->response;
    }

    /**
     * Handle all HTTP request.
     *
     * @return null|HttpResponse
     */
    public function serve()
    {
        $method = $this->getRequest()->method();

        if ( ! in_array($method, $this->request->allowedHttpVerbs())) {
            return $this->response->send(null, HttpResponse::HTTP_METHOD_NOT_ALLOWED);
        }

        $method = 'handle' . ucfirst(strtolower($method));

        $this->{$method}();

        $this->exit();
    }

    /**
     * Exit from current php process.
     *
     * @codeCoverageIgnore
     */
    protected function exit()
    {
        exit(0);
    }

    /**
     * Handle OPTIONS request.
     *
     * @return HttpResponse
     */
    protected function handleOptions() : HttpResponse
    {
        return $this->response->send(
            null,
            HttpResponse::HTTP_OK,
            [
                'Allow' => $this->request->allowedHttpVerbs(),
                'Tus-Version' => self::TUS_PROTOCOL_VERSION,
                'Tus-Extension' => implode(',', [
                    self::TUS_EXTENSION_CREATION,
                    self::TUS_EXTENSION_TERMINATION,
                    self::TUS_EXTENSION_CHECKSUM,
                ]),
                'Tus-Checksum-Algorithm' => $this->getSupportedHashAlgorithms(),
            ]
        );
    }

    /**
     * Handle HEAD request.
     *
     * @return HttpResponse
     */
    protected function handleHead() : HttpResponse
    {
        $checksum = $this->request->checksum();

        if ( ! $this->cache->get($checksum)) {
            return $this->response->send(null, HttpResponse::HTTP_NOT_FOUND);
        }

        $offset = $this->cache->get($checksum)['offset'] ?? false;

        if (false === $offset) {
            return $this->response->send(null, HttpResponse::HTTP_GONE);
        }

        return $this->response->send(null, HttpResponse::HTTP_OK, [
            'Upload-Offset' => (int) $offset,
            'Cache-Control' => 'no-store',
            'Tus-Resumable' => self::TUS_PROTOCOL_VERSION,
        ]);
    }

    /**
     * Handle POST request.
     *
     * @return HttpResponse
     */
    protected function handlePost() : HttpResponse
    {
        $fileName = $this->getRequest()->extractFileName();

        if (empty($fileName)) {
            return $this->response->send(null, HttpResponse::HTTP_BAD_REQUEST);
        }

        $checksum = $this->getRequest()->header('Checksum');
        $location = $this->getRequest()->url() . "/" . basename($this->uploadDir) . "/" . $fileName;

        $file = $this->buildFile([
            'name' => $fileName,
            'offset' => 0,
            'size' => $this->getRequest()->header('Upload-Length'),
            'file_path' => $this->uploadDir . DIRECTORY_SEPARATOR . $fileName,
            'location' => $location,
        ])->setChecksum($checksum);

        $this->cache->set($checksum, $file->details());

        return $this->response->send(
            ['data' => ['checksum' => $checksum]],
            HttpResponse::HTTP_CREATED,
            [
                'Location' => $location,
                'Upload-Expires' => $this->cache->get($checksum)['expires_at'],
                'Tus-Resumable' => self::TUS_PROTOCOL_VERSION,
            ]
        );
    }

    /**
     * Handle PATCH request.
     *
     * @return HttpResponse
     */
    protected function handlePatch() : HttpResponse
    {
        $checksum = $this->request->checksum();

        if ( ! $this->cache->get($checksum)) {
            return $this->response->send(null, HttpResponse::HTTP_GONE);
        }

        $meta = $this->cache->get($checksum);
        $file = $this->buildFile($meta);

        try {
            $offset = $file->setChecksum($checksum)->upload($file->getFileSize());
        } catch (FileException $e) {
            return $this->response->send($e->getMessage(), HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (OutOfRangeException $e) {
            return $this->response->send(null, HttpResponse::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE);
        } catch (ConnectionException $e) {
            return $this->response->send(null, HttpResponse::HTTP_CONTINUE);
        }

        return $this->response->send(null, HttpResponse::HTTP_NO_CONTENT, [
            'Upload-Expires' => $this->cache->get($checksum)['expires_at'],
            'Upload-Offset' => $offset,
            'Tus-Resumable' => self::TUS_PROTOCOL_VERSION,
        ]);
    }

    /**
     * Handle GET request.
     *
     * @return BinaryFileResponse|HttpResponse
     */
    protected function handleGet()
    {
        $checksum = $this->request->checksum();

        if (empty($checksum)) {
            return $this->response->send('400 bad request.', HttpResponse::HTTP_BAD_REQUEST);
        }

        $fileMeta = $this->cache->get($checksum);

        if ( ! $fileMeta) {
            return $this->response->send('404 upload not found.', HttpResponse::HTTP_NOT_FOUND);
        }

        $resource = $fileMeta['file_path'] ?? null;
        $fileName = $fileMeta['name'] ?? null;

        if ( ! $resource || ! file_exists($resource)) {
            return $this->response->send('404 upload not found.', HttpResponse::HTTP_NOT_FOUND);
        }

        return $this->response->download($resource, $fileName);
    }

    /**
     * Handle DELETE request.
     *
     * @return HttpResponse
     */
    protected function handleDelete() : HttpResponse
    {
        $checksum = $this->request->checksum();
        $fileMeta = $this->cache->get($checksum);
        $resource = $fileMeta['file_path'] ?? null;

        if ( ! $resource) {
            return $this->response->send(null, HttpResponse::HTTP_NOT_FOUND);
        }

        $isDeleted = $this->cache->delete($checksum);

        if ( ! $isDeleted || ! file_exists($resource)) {
            return $this->response->send(null, HttpResponse::HTTP_GONE);
        }

        return $this->response->send(null, HttpResponse::HTTP_NO_CONTENT, [
            'Tus-Resumable' => self::TUS_PROTOCOL_VERSION,
            'Tus-Extension' => self::TUS_EXTENSION_TERMINATION,
        ]);
    }

    /**
     * Build file object.
     *
     * @param array $meta
     *
     * @return File
     */
    protected function buildFile(array $meta) : File
    {
        return (new File($meta['name'], $this->cache))
            ->setMeta($meta['offset'], $meta['size'], $meta['file_path'], $meta['location']);
    }

    /**
     * Get list of supported hash algorithms.
     *
     * @return string
     */
    protected function getSupportedHashAlgorithms()
    {
        $supportedAlgorithms = hash_algos();

        $algorithms = '';
        foreach ($supportedAlgorithms as $hashAlgo) {
            if (false !== strpos($hashAlgo, ',')) {
                $algorithms .= ",'{$hashAlgo}'";
            } else {
                $algorithms .= ",$hashAlgo";
            }
        }

        return $algorithms;
    }

    /**
     * No other methods are allowed.
     *
     * @param string $method
     * @param array  $params
     *
     * @return HttpResponse|BinaryFileResponse
     */
    public function __call(string $method, array $params)
    {
        return $this->response->send(null, HttpResponse::HTTP_BAD_REQUEST);
    }
}

<?php

namespace TusPhp\Tus;

use TusPhp\File;
use Carbon\Carbon;
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

    /** @const Tus Expiration Extension */
    const TUS_EXTENSION_EXPIRATION = 'expiration';

    /** @const 460 Checksum Mismatch */
    const HTTP_CHECKSUM_MISMATCH = 460;

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
                    self::TUS_EXTENSION_EXPIRATION,
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

        $checksum = $this->getUploadChecksum();
        $location = $this->getRequest()->url() . '/' . basename($this->uploadDir) . '/' . $fileName;

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
            $fileSize = $file->getFileSize();
            $offset   = $file->setChecksum($checksum)->upload($fileSize);

            // If upload is done, verify checksum.
            if ($offset === $fileSize && $checksum !== $this->getUploadChecksum()) {
                return $this->response->send(null, self::HTTP_CHECKSUM_MISMATCH);
            }
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

        unlink($resource);

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

        $algorithms = [];
        foreach ($supportedAlgorithms as $hashAlgo) {
            if (false !== strpos($hashAlgo, ',')) {
                $algorithms[] = "'{$hashAlgo}'";
            } else {
                $algorithms[] = $hashAlgo;
            }
        }

        return implode(',', $algorithms);
    }

    /**
     * Verify and get upload checksum from header.
     *
     * @return string|HttpResponse
     */
    protected function getUploadChecksum()
    {
        $checksumHeader = $this->getRequest()->header('Upload-Checksum');

        if (empty($checksumHeader)) {
            return $this->response->send(null, HttpResponse::HTTP_BAD_REQUEST);
        }

        list($checksumAlgorithm, $checksum) = explode(' ', $checksumHeader);

        $checksum = base64_decode($checksum);

        if ( ! in_array($checksumAlgorithm, hash_algos()) || false === $checksum) {
            return $this->response->send(null, HttpResponse::HTTP_BAD_REQUEST);
        }

        return $checksum;
    }

    /**
     * Get expired and incomplete uploads.
     *
     * @param array|null $contents
     *
     * @return bool
     */
    protected function isExpired($contents) : bool
    {
        $isExpired = empty($contents['expires_at']) || Carbon::parse($contents['expires_at'])->lt(Carbon::now());

        if ($isExpired && $contents['offset'] !== $contents['size']) {
            return true;
        }

        return false;
    }

    /**
     * Delete expired resources.
     *
     * @return array
     */
    public function handleExpiration()
    {
        $deleted   = [];
        $cacheKeys = $this->cache->keys();

        foreach ($cacheKeys as $key) {
            $fileMeta = $this->cache->get($key, true);

            if ( ! $this->isExpired($fileMeta)) {
                continue;
            }

            $cacheDeleted = $this->cache->delete($key);

            if ( ! $cacheDeleted) {
                continue;
            }

            if (file_exists($fileMeta['file_path']) && is_writable($fileMeta['file_path'])) {
                unlink($fileMeta['file_path']);
            }

            $deleted[] = $fileMeta;
        }

        return $deleted;
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

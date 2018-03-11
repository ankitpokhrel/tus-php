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
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class Server extends AbstractTus
{
    /** @const string Tus Creation Extension */
    const TUS_EXTENSION_CREATION = 'creation';

    /** @const string Tus Termination Extension */
    const TUS_EXTENSION_TERMINATION = 'termination';

    /** @const string Tus Checksum Extension */
    const TUS_EXTENSION_CHECKSUM = 'checksum';

    /** @const string Tus Expiration Extension */
    const TUS_EXTENSION_EXPIRATION = 'expiration';

    /** @const string Tus Concatenation Extension */
    const TUS_EXTENSION_CONCATENATION = 'concatenation';

    /** @const int 460 Checksum Mismatch */
    const HTTP_CHECKSUM_MISMATCH = 460;

    /** @const string Default checksum algorithm */
    const DEFAULT_CHECKSUM_ALGORITHM = 'sha256';

    /** @const int 24 hours access control max age header */
    const HEADER_ACCESS_CONTROL_MAX_AGE = 86400;

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
     * Get file checksum.
     *
     * @param string $filePath
     *
     * @return string
     */
    public function getServerChecksum(string $filePath)
    {
        return hash_file($this->getChecksumAlgorithm(), $filePath);
    }

    /**
     * Get checksum algorithm.
     *
     * @return string|null
     */
    public function getChecksumAlgorithm()
    {
        $checksumHeader = $this->getRequest()->header('Upload-Checksum');

        if (empty($checksumHeader)) {
            return self::DEFAULT_CHECKSUM_ALGORITHM;
        }

        list($checksumAlgorithm) = explode(' ', $checksumHeader);

        return $checksumAlgorithm;
    }

    /**
     * Handle all HTTP request.
     *
     * @return null|HttpResponse
     */
    public function serve()
    {
        $requestMethod    = $this->getRequest()->method();
        $allowedHttpVerbs = $this->getRequest()->allowedHttpVerbs();

        $globalHeaders = [
            'Access-Control-Allow-Origin' => $this->request->header('Origin'),
            'Access-Control-Allow-Methods' => implode(',', $allowedHttpVerbs),
            'Access-Control-Allow-Headers' => 'Origin, X-Requested-With, Content-Type, Upload-Key, Upload-Checksum, Upload-Length, Upload-Offset, Tus-Resumable, Upload-Metadata',
            'Access-Control-Max-Age' => self::HEADER_ACCESS_CONTROL_MAX_AGE,
        ];

        if (HttpRequest::METHOD_OPTIONS !== $requestMethod) {
            $globalHeaders['Tus-Resumable'] = self::TUS_PROTOCOL_VERSION;
        }

        $this->getResponse()->setHeaders($globalHeaders);

        if ( ! in_array($requestMethod, $allowedHttpVerbs)) {
            return $this->response->send(null, HttpResponse::HTTP_METHOD_NOT_ALLOWED);
        }

        $method = 'handle' . ucfirst(strtolower($requestMethod));

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
                'Allow' => implode(',', $this->request->allowedHttpVerbs()),
                'Tus-Version' => self::TUS_PROTOCOL_VERSION,
                'Tus-Extension' => implode(',', [
                    self::TUS_EXTENSION_CREATION,
                    self::TUS_EXTENSION_TERMINATION,
                    self::TUS_EXTENSION_CHECKSUM,
                    self::TUS_EXTENSION_EXPIRATION,
                    self::TUS_EXTENSION_CONCATENATION,
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
        $key = $this->request->key();

        if ( ! $fileMeta = $this->cache->get($key)) {
            return $this->response->send(null, HttpResponse::HTTP_NOT_FOUND);
        }

        $offset = $fileMeta['offset'] ?? false;

        if (false === $offset) {
            return $this->response->send(null, HttpResponse::HTTP_GONE);
        }

        return $this->response->send(null, HttpResponse::HTTP_OK, $this->getHeadersForHeadRequest($fileMeta));
    }

    /**
     * Handle POST request.
     *
     * @return HttpResponse
     */
    protected function handlePost() : HttpResponse
    {
        $fileName   = $this->getRequest()->extractFileName();
        $uploadType = self::UPLOAD_TYPE_NORMAL;

        if (empty($fileName)) {
            return $this->response->send(null, HttpResponse::HTTP_BAD_REQUEST);
        }

        $uploadKey = $this->getUploadKey();
        $filePath  = $this->uploadDir . DIRECTORY_SEPARATOR . $fileName;

        if ($this->getRequest()->isFinal()) {
            return $this->handleConcatenation($fileName, $filePath);
        }

        if ($this->getRequest()->isPartial()) {
            $filePath   = $this->getPathForPartialUpload($uploadKey) . $fileName;
            $uploadType = self::UPLOAD_TYPE_PARTIAL;
        }

        $checksum = $this->getClientChecksum();
        $location = $this->getRequest()->url() . '/' . basename($this->uploadDir) . '/' . $fileName;

        $file = $this->buildFile([
            'name' => $fileName,
            'offset' => 0,
            'size' => $this->getRequest()->header('Upload-Length'),
            'file_path' => $filePath,
            'location' => $location,
        ])->setChecksum($checksum);

        $this->cache->set($uploadKey, $file->details() + ['upload_type' => $uploadType]);

        return $this->response->send(
            ['data' => ['checksum' => $checksum]],
            HttpResponse::HTTP_CREATED,
            [
                'Access-Control-Expose-Headers' => 'Location',
                'Location' => $location,
                'Upload-Expires' => $this->cache->get($uploadKey)['expires_at'],
            ]
        );
    }

    /**
     * Handle file concatenation.
     *
     * @param string $fileName
     * @param string $filePath
     *
     * @return HttpResponse
     */
    protected function handleConcatenation(string $fileName, string $filePath) : HttpResponse
    {
        $partials  = $this->getRequest()->extractPartials();
        $files     = $this->getPartialsMeta($partials);
        $filePaths = array_column($files, 'file_path');
        $location  = $this->getRequest()->url() . '/' . basename($this->uploadDir) . '/' . $fileName;

        $file = $this->buildFile([
            'name' => $fileName,
            'offset' => 0,
            'size' => 0,
            'file_path' => $filePath,
            'location' => $location,
        ])->setFilePath($filePath);

        $file->setOffset($file->merge($files));

        // Verify checksum.
        $checksum = $this->getServerChecksum($filePath);

        if ($checksum !== $this->getClientChecksum()) {
            return $this->response->send(null, self::HTTP_CHECKSUM_MISMATCH);
        }

        $this->cache->set($this->getUploadKey(), $file->details() + ['upload_type' => self::UPLOAD_TYPE_FINAL]);

        // Cleanup.
        if ($file->delete($filePaths, true)) {
            $this->cache->deleteAll($partials);
        }

        return $this->response->send(
            ['data' => ['checksum' => $checksum]],
            HttpResponse::HTTP_CREATED,
            [
                'Access-Control-Expose-Headers' => 'Location',
                'Location' => $location,
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
        $uploadKey = $this->request->key();

        if ( ! $meta = $this->cache->get($uploadKey)) {
            return $this->response->send(null, HttpResponse::HTTP_GONE);
        }

        if (self::UPLOAD_TYPE_FINAL === $meta['upload_type']) {
            return $this->response->send(null, HttpResponse::HTTP_FORBIDDEN);
        }

        $file     = $this->buildFile($meta);
        $checksum = $meta['checksum'];

        try {
            $fileSize = $file->getFileSize();
            $offset   = $file->setKey($uploadKey)->setChecksum($checksum)->upload($fileSize);

            // If upload is done, verify checksum.
            if ($offset === $fileSize && $checksum !== $this->getServerChecksum($meta['file_path'])) {
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
            'Upload-Expires' => $this->cache->get($uploadKey)['expires_at'],
            'Upload-Offset' => $offset,
        ]);
    }

    /**
     * Handle GET request.
     *
     * @return BinaryFileResponse|HttpResponse
     */
    protected function handleGet()
    {
        $key = $this->request->key();

        if (empty($key)) {
            return $this->response->send('400 bad request.', HttpResponse::HTTP_BAD_REQUEST);
        }

        if ( ! $fileMeta = $this->cache->get($key)) {
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
        $key      = $this->request->key();
        $fileMeta = $this->cache->get($key);
        $resource = $fileMeta['file_path'] ?? null;

        if ( ! $resource) {
            return $this->response->send(null, HttpResponse::HTTP_NOT_FOUND);
        }

        $isDeleted = $this->cache->delete($key);

        if ( ! $isDeleted || ! file_exists($resource)) {
            return $this->response->send(null, HttpResponse::HTTP_GONE);
        }

        unlink($resource);

        return $this->response->send(null, HttpResponse::HTTP_NO_CONTENT, [
            'Tus-Extension' => self::TUS_EXTENSION_TERMINATION,
        ]);
    }

    /**
     * Get required headers for head request.
     *
     * @param array $fileMeta
     *
     * @return array
     */
    protected function getHeadersForHeadRequest(array $fileMeta) : array
    {
        $headers = [
            'Upload-Offset' => (int) $fileMeta['offset'],
            'Cache-Control' => 'no-store',
        ];

        if (self::UPLOAD_TYPE_FINAL === $fileMeta['upload_type'] && $fileMeta['size'] !== $fileMeta['offset']) {
            unset($headers['Upload-Offset']);
        }

        if (self::UPLOAD_TYPE_NORMAL !== $fileMeta['upload_type']) {
            $headers += ['Upload-Concat' => $fileMeta['upload_type']];
        }

        return $headers;
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
        $file = new File($meta['name'], $this->cache);

        if (array_key_exists('offset', $meta)) {
            $file->setMeta($meta['offset'], $meta['size'], $meta['file_path'], $meta['location']);
        }

        return $file;
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
     * Get upload key from header.
     *
     * @return string|HttpResponse
     */
    protected function getUploadKey()
    {
        $key = $this->getRequest()->header('Upload-Key');

        if (empty($key)) {
            return $this->response->send(null, HttpResponse::HTTP_BAD_REQUEST);
        }

        return base64_decode($key);
    }

    /**
     * Verify and get upload checksum from header.
     *
     * @return string|HttpResponse
     */
    protected function getClientChecksum()
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
     * Get expired but incomplete uploads.
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
     * Get path for partial upload.
     *
     * @param string $key
     *
     * @return string
     */
    protected function getPathForPartialUpload(string $key) : string
    {
        list($actualKey) = explode(self::PARTIAL_UPLOAD_NAME_SEPARATOR, $key);

        $path = $this->uploadDir . DIRECTORY_SEPARATOR . $actualKey . DIRECTORY_SEPARATOR;

        if ( ! file_exists($path)) {
            mkdir($path);
        }

        return $path;
    }

    /**
     * Get metadata of partials.
     *
     * @param array $partials
     *
     * @return array
     */
    protected function getPartialsMeta(array $partials)
    {
        $files = [];

        foreach ($partials as $partial) {
            $fileMeta = $this->getCache()->get($partial);

            $files[] = $fileMeta;
        }

        return $files;
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

            if ( ! $this->cache->delete($key)) {
                continue;
            }

            if (is_writable($fileMeta['file_path'])) {
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

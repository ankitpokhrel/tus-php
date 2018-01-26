<?php

namespace TusPhp\Tus;

use TusPhp\File;
use TusPhp\Cache\Cacheable;
use TusPhp\Exception\Exception;
use TusPhp\Exception\FileException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use TusPhp\Exception\ConnectionException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Response as HttpResponse;

class Client extends AbstractTus
{
    /** @var GuzzleClient */
    protected $client;

    /** @var string */
    protected $apiPath = '/files';

    /** @var string */
    protected $filePath;

    /** @var int */
    protected $fileSize;

    /** @var string */
    protected $fileName;

    /** @var string */
    protected $checksum;

    /** @var int */
    protected $offset = -1;

    /** @var bool */
    protected $partial = false;

    /** @var string */
    protected $checksumAlgorithm = 'sha256';

    /**
     * Client constructor.
     *
     * @param string           $baseUrl
     * @param Cacheable|string $cacheAdapter
     */
    public function __construct(string $baseUrl, $cacheAdapter = 'file')
    {
        $this->client = new GuzzleClient([
            'base_uri' => $baseUrl,
        ]);

        $this->setCache($cacheAdapter);
    }

    /**
     * Set file properties.
     *
     * @param string $file File path.
     * @param string $name File name.
     *
     * @return Client
     */
    public function file(string $file, string $name = null) : self
    {
        $this->filePath = $file;

        if ( ! file_exists($file) || ! is_readable($file)) {
            throw new FileException('Cannot read file: ' . $file);
        }

        $this->fileName = $name ?? basename($this->filePath);
        $this->fileSize = filesize($file);

        return $this;
    }

    /**
     * Get file path.
     *
     * @return string|null
     */
    public function getFilePath()
    {
        return $this->filePath;
    }

    /**
     * Set file name.
     *
     * @param string $name
     *
     * @return Client
     */
    public function setFileName(string $name) : self
    {
        $this->fileName = $name;

        return $this;
    }

    /**
     * Get file name.
     *
     * @return string|null
     */
    public function getFileName()
    {
        return $this->fileName;
    }

    /**
     * Get file size.
     *
     * @return int|null
     */
    public function getFileSize()
    {
        return $this->fileSize;
    }

    /**
     * Set API path.
     *
     * @param string $path
     *
     * @return Client
     */
    public function setApiPath(string $path) : self
    {
        $this->apiPath = $path;

        return $this;
    }

    /**
     * Get API path.
     *
     * @return string
     */
    public function getApiPath() : string
    {
        return $this->apiPath;
    }

    /**
     * Get guzzle client.
     *
     * @return GuzzleClient
     */
    public function getClient() : GuzzleClient
    {
        return $this->client;
    }

    /**
     * Get checksum.
     *
     * @return string
     */
    public function getChecksum() : string
    {
        if (empty($this->checksum)) {
            $this->checksum = hash_file($this->getChecksumAlgorithm(), $this->getFilePath());
        }

        return $this->checksum;
    }

    /**
     * Set checksum algorithm.
     *
     * @param string $algorithm
     *
     * @return Client
     */
    public function setChecksumAlgorithm(string $algorithm) : self
    {
        $this->checksumAlgorithm = $algorithm;

        return $this;
    }

    /**
     * Get checksum algorithm.
     *
     * @return string
     */
    public function getChecksumAlgorithm() : string
    {
        return $this->checksumAlgorithm;
    }

    /**
     * Check if this is a partial upload request.
     *
     * @return bool
     */
    public function isPartial() : bool
    {
        return $this->partial;
    }

    /**
     * Set offset and force this to be a partial upload request.
     *
     * @param int $offset
     *
     * @return $this
     */
    public function seek(int $offset)
    {
        $this->offset = $offset;

        $this->partial();

        return $this;
    }

    /**
     * Upload file.
     *
     * @param int $bytes Bytes to upload
     *
     * @throws ConnectionException
     *
     * @return int
     */
    public function upload(int $bytes = -1) : int
    {
        $bytes    = $bytes < 0 ? $this->getFileSize() : $bytes;
        $checksum = $this->getChecksum();

        try {
            // Check if this upload exists with HEAD request
            $this->sendHeadRequest($checksum);
        } catch (FileException | ClientException $e) {
            $this->create($checksum);
        } catch (ConnectException $e) {
            throw new ConnectionException("Couldn't connect to server.");
        }

        // Now, resume upload with PATCH request
        return $this->sendPatchRequest($checksum, $bytes);
    }

    /**
     * Returns offset if file is partially uploaded.
     *
     * @return bool|int
     */
    public function getOffset()
    {
        $checksum = $this->getChecksum();

        try {
            $offset = $this->sendHeadRequest($checksum);
        } catch (FileException | ClientException $e) {
            return false;
        }

        return $offset;
    }

    /**
     * Create resource with POST request.
     *
     * @param string $checksum
     *
     * @throws FileException
     *
     * @return string
     */
    public function create(string $checksum) : string
    {
        $headers = [
            'Upload-Length' => $this->fileSize,
            'Upload-Checksum' => $this->getUploadChecksumHeader($checksum),
            'Upload-Metadata' => 'filename ' . base64_encode($this->fileName),
        ];

        if ($this->isPartial()) {
            $headers += ['Upload-Concat' => 'partial'];
        }

        $response = $this->getClient()->post($this->apiPath, [
            'headers' => $headers,
        ]);

        $data       = json_decode($response->getBody(), true);
        $checksum   = $data['data']['checksum'] ?? null;
        $statusCode = $response->getStatusCode();

        if (HttpResponse::HTTP_CREATED !== $statusCode || ! $checksum) {
            throw new FileException('Unable to create resource.');
        }

        return $checksum;
    }

    /**
     * Send DELETE request.
     *
     * @param string $checksum
     *
     * @throws FileException
     *
     * @return void
     */
    public function delete(string $checksum)
    {
        try {
            $this->getClient()->delete($this->apiPath . '/' . $checksum, [
                'headers' => [
                    'Tus-Resumable' => self::TUS_PROTOCOL_VERSION,
                ],
            ]);
        } catch (ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();

            if (HttpResponse::HTTP_NOT_FOUND === $statusCode || HttpResponse::HTTP_GONE === $statusCode) {
                throw new FileException('File not found.');
            }
        }
    }

    /**
     * Set as partial request.
     *
     * @param bool $state
     */
    protected function partial(bool $state = true)
    {
        $this->partial = $state;

        if ( ! $this->partial) {
            return;
        }

        $checksum = $this->getChecksum();

        if (false !== strpos($checksum, self::PARTIAL_UPLOAD_NAME_SEPARATOR)) {
            list($checksum) = explode(self::PARTIAL_UPLOAD_NAME_SEPARATOR, $checksum);
        }

        $this->checksum = $checksum . uniqid(self::PARTIAL_UPLOAD_NAME_SEPARATOR);
    }

    /**
     * Send HEAD request.
     *
     * @param string $checksum
     *
     * @throws FileException
     *
     * @return int
     */
    protected function sendHeadRequest(string $checksum) : int
    {
        $response = $this->getClient()->head($this->apiPath . '/' . $checksum);

        $statusCode = $response->getStatusCode();

        if (HttpResponse::HTTP_OK !== $statusCode) {
            throw new FileException('File not found.');
        }

        return (int) current($response->getHeader('upload-offset'));
    }

    /**
     * Send PATCH request.
     *
     * @param string $checksum
     * @param int    $bytes
     *
     * @throws Exception
     * @throws FileException
     * @throws ConnectionException
     *
     * @return int
     */
    protected function sendPatchRequest(string $checksum, int $bytes) : int
    {
        $data    = $this->getData($checksum, $bytes);
        $headers = [
            'Content-Type' => 'application/offset+octet-stream',
            'Content-Length' => strlen($data),
            'Upload-Checksum' => $this->getUploadChecksumHeader($checksum),
        ];

        if ($this->isPartial()) {
            $headers += ['Upload-Concat' => 'partial'];
        }

        try {
            $response = $this->getClient()->patch($this->apiPath . '/' . $checksum, [
                'body' => $data,
                'headers' => $headers,
            ]);

            return (int) current($response->getHeader('upload-offset'));
        } catch (ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();

            if (HttpResponse::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE === $statusCode) {
                throw new FileException('The uploaded file is corrupt.');
            }

            if (HttpResponse::HTTP_CONTINUE === $statusCode) {
                throw new ConnectionException('Connection aborted by user.');
            }

            throw new Exception($e->getResponse()->getBody(), $statusCode);
        } catch (ConnectException $e) {
            throw new ConnectionException("Couldn't connect to server.");
        }
    }

    /**
     * Get X bytes of data from file.
     *
     * @param string $checksum
     * @param int    $bytes
     *
     * @return string
     */
    protected function getData(string $checksum, int $bytes) : string
    {
        $file   = new File;
        $handle = $file->open($this->getFilePath(), $file::READ_BINARY);
        $offset = $this->offset;

        if ($offset < 0) {
            $fileMeta = $this->getCache()->get($checksum);
            $offset   = $fileMeta['offset'];
        }

        $file->seek($handle, $offset);

        $data = $file->read($handle, $bytes);

        $file->close($handle);

        return (string) $data;
    }

    /**
     * Get upload checksum header.
     *
     * @param string|null $checksum
     *
     * @return string
     */
    protected function getUploadChecksumHeader(string $checksum = null) : string
    {
        if (empty($checksum)) {
            $checksum = $this->getChecksum();
        }

        return $this->getChecksumAlgorithm() . ' ' . base64_encode($checksum);
    }
}

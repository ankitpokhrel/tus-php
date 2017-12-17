<?php

namespace TusPhp\Tus;

use TusPhp\File;
use TusPhp\Cache\CacheFactory;
use TusPhp\Exception\FileException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use TusPhp\Exception\ConnectionException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Response as HttpResponse;

class Client extends AbstractTus
{
    /** @const Checksum algorithm. */
    const HASH_ALGORITHM = 'sha256';

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

    /**
     * Client constructor.
     *
     * @param string $endpoint
     * @param string $cacheAdapter
     */
    public function __construct(string $endpoint, string $cacheAdapter = 'file')
    {
        $this->client = new GuzzleClient([
            'base_uri' => $endpoint,
        ]);

        $this->cache = CacheFactory::make($cacheAdapter);
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

        if (! file_exists($file) || ! is_readable($file)) {
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
        $checksum = hash_file(self::HASH_ALGORITHM, $this->getFilePath());

        try {
            // Check if this upload exists with HEAD request
            $this->sendHeadRequest($checksum);
        } catch (FileException $e) {
            $this->create();
        } catch (ClientException $e) {
            $this->create();
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
        $checksum = hash_file(self::HASH_ALGORITHM, $this->getFilePath());

        try {
            $offset = $this->sendHeadRequest($checksum);
        } catch (FileException $e) {
            return false;
        } catch (ClientException $e) {
            return false;
        }

        return $offset;
    }

    /**
     * Create resource with POST request.
     *
     * @throws FileException
     *
     * @return string
     */
    public function create() : string
    {
        $response = $this->getClient()->post($this->apiPath, [
            'headers' => [
                'Checksum' => hash_file(self::HASH_ALGORITHM, $this->filePath),
                'Upload-Length' => $this->fileSize,
                'Upload-Metadata' => 'filename ' . base64_encode($this->fileName),
            ],
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
     * @throws FileException
     * @throws ConnectionException
     *
     * @return int
     */
    protected function sendPatchRequest(string $checksum, int $bytes) : int
    {
        $data = $this->getData($checksum, $bytes);

        $response = $this->getClient()->patch($this->apiPath . '/' . $checksum, [
            'body' => $data,
            'headers' => [
                'Content-Type' => 'application/offset+octet-stream',
                'Content-Length' => strlen($data),
            ],
        ]);

        $statusCode = $response->getStatusCode();

        if (HttpResponse::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE === $statusCode) {
            throw new FileException('The uploaded file is corrupt.');
        }

        if (HttpResponse::HTTP_CONTINUE === $statusCode) {
            throw new ConnectionException('Connection aborted by user.');
        }

        return (int) current($response->getHeader('upload-offset'));
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
    protected function sendDeleteRequest(string $checksum)
    {
        $response = $this->getClient()->delete($this->apiPath . '/' . $checksum, [
            'headers' => [
                'Tus-Resumable' => self::TUS_PROTOCOL_VERSION,
            ],
        ]);

        if (HttpResponse::HTTP_NOT_FOUND === $response->getStatusCode()) {
            throw new FileException('File not found.');
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
        $file = new File();

        $handle   = $file->open($this->getFilePath(), $file::READ_BINARY);
        $fileMeta = $this->getCache()->get($checksum);

        $file->seek($handle, $fileMeta['offset']);

        $data = $file->read($handle, $bytes);

        $file->close($handle);

        return (string) $data;
    }
}

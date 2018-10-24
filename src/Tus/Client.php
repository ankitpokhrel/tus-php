<?php

namespace TusPhp\Tus;

use TusPhp\File;
use TusPhp\Exception\Exception;
use TusPhp\Exception\FileException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use TusPhp\Exception\ConnectionException;
use GuzzleHttp\Exception\ConnectException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class Client extends AbstractTus
{
    /** @var GuzzleClient */
    protected $client;

    /** @var string */
    protected $filePath;

    /** @var int */
    protected $fileSize = 0;

    /** @var string */
    protected $fileName;

    /** @var string */
    protected $key;

    /** @var string */
    protected $checksum;

    /** @var int */
    protected $partialOffset = -1;

    /** @var bool */
    protected $partial = false;

    /** @var string */
    protected $checksumAlgorithm = 'sha256';

    /**
     * Client constructor.
     *
     * @param string $baseUri
     * @param array  $options
     */
    public function __construct(string $baseUri, array $options = [])
    {
        $options['headers'] = [
            'Tus-Resumable' => self::TUS_PROTOCOL_VERSION,
        ] + ($options['headers'] ?? []);

        $this->client = new GuzzleClient(
            ['base_uri' => $baseUri] + $options
        );
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
    public function getFilePath() : ?string
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
    public function getFileName() : ?string
    {
        return $this->fileName;
    }

    /**
     * Get file size.
     *
     * @return int
     */
    public function getFileSize() : int
    {
        return $this->fileSize;
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
     * Set key.
     *
     * @param string $key
     *
     * @return Client
     */
    public function setKey(string $key) : self
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Get key.
     *
     * @return string
     */
    public function getKey() : string
    {
        return $this->key;
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
     * Get partial offset.
     *
     * @return int
     */
    public function getPartialOffset() : int
    {
        return $this->partialOffset;
    }

    /**
     * Set offset and force this to be a partial upload request.
     *
     * @param int $offset
     *
     * @return self
     */
    public function seek(int $offset) : self
    {
        $this->partialOffset = $offset;

        $this->partial();

        return $this;
    }

    /**
     * Upload file.
     *
     * @param int $bytes Bytes to upload
     *
     * @throws Exception
     * @throws ConnectionException
     *
     * @return int
     */
    public function upload(int $bytes = -1) : int
    {
        $key    = $this->getKey();
        $bytes  = $bytes < 0 ? $this->getFileSize() : $bytes;
        $offset = $this->partialOffset < 0 ? 0 : $this->partialOffset;

        try {
            // Check if this upload exists with HEAD request.
            $offset = $this->sendHeadRequest($key);
        } catch (FileException | ClientException $e) {
            $this->create($key);
        } catch (ConnectException $e) {
            throw new ConnectionException("Couldn't connect to server.");
        }

        // Now, resume upload with PATCH request.
        return $this->sendPatchRequest($bytes, $offset);
    }

    /**
     * Returns offset if file is partially uploaded.
     *
     * @return bool|int
     */
    public function getOffset()
    {
        $key = $this->getKey();

        try {
            $offset = $this->sendHeadRequest($key);
        } catch (FileException | ClientException $e) {
            return false;
        }

        return $offset;
    }

    /**
     * Create resource with POST request.
     *
     * @param string $key
     *
     * @throws FileException
     *
     * @return void
     */
    public function create(string $key)
    {
        $headers = [
            'Upload-Length' => $this->fileSize,
            'Upload-Key' => $key,
            'Upload-Checksum' => $this->getUploadChecksumHeader(),
            'Upload-Metadata' => 'filename ' . base64_encode($this->fileName),
        ];

        if ($this->isPartial()) {
            $headers += ['Upload-Concat' => 'partial'];
        }

        $response = $this->getClient()->post($this->apiPath, [
            'headers' => $headers,
        ]);

        $statusCode = $response->getStatusCode();

        if (HttpResponse::HTTP_CREATED !== $statusCode) {
            throw new FileException('Unable to create resource.');
        }
    }

    /**
     * Concatenate 2 or more partial uploads.
     *
     * @param string $key
     * @param mixed  $partials
     *
     * @return string
     */
    public function concat(string $key, ...$partials) : string
    {
        $response = $this->getClient()->post($this->apiPath, [
            'headers' => [
                'Upload-Length' => $this->fileSize,
                'Upload-Key' => $key,
                'Upload-Checksum' => $this->getUploadChecksumHeader(),
                'Upload-Metadata' => 'filename ' . base64_encode($this->fileName),
                'Upload-Concat' => self::UPLOAD_TYPE_FINAL . ';' . implode(' ', $partials),
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
     * Send DELETE request.
     *
     * @param string $key
     *
     * @throws FileException
     *
     * @return void
     */
    public function delete(string $key)
    {
        try {
            $this->getClient()->delete($this->apiPath . '/' . $key);
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
     *
     * @return void
     */
    protected function partial(bool $state = true)
    {
        $this->partial = $state;

        if ( ! $this->partial) {
            return;
        }

        $key = $this->getKey();

        if (false !== strpos($key, self::PARTIAL_UPLOAD_NAME_SEPARATOR)) {
            list($key, /* $partialKey */) = explode(self::PARTIAL_UPLOAD_NAME_SEPARATOR, $key);
        }

        $this->key = $key . uniqid(self::PARTIAL_UPLOAD_NAME_SEPARATOR);
    }

    /**
     * Send HEAD request.
     *
     * @param string $key
     *
     * @throws FileException
     *
     * @return int
     */
    protected function sendHeadRequest(string $key) : int
    {
        $response   = $this->getClient()->head($this->apiPath . '/' . $key);
        $statusCode = $response->getStatusCode();

        if (HttpResponse::HTTP_OK !== $statusCode) {
            throw new FileException('File not found.');
        }

        return (int) current($response->getHeader('upload-offset'));
    }

    /**
     * Send PATCH request.
     *
     * @param int $bytes
     * @param int $offset
     *
     * @throws Exception
     * @throws FileException
     * @throws ConnectionException
     *
     * @return int
     */
    protected function sendPatchRequest(int $bytes, int $offset) : int
    {
        $data    = $this->getData($offset, $bytes);
        $headers = [
            'Content-Type' => self::HEADER_CONTENT_TYPE,
            'Content-Length' => strlen($data),
            'Upload-Checksum' => $this->getUploadChecksumHeader(),
        ];

        if ($this->isPartial()) {
            $headers += ['Upload-Concat' => self::UPLOAD_TYPE_PARTIAL];
        } else {
            $headers += ['Upload-Offset' => $offset];
        }

        try {
            $response = $this->getClient()->patch($this->apiPath . '/' . $this->getKey(), [
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

            if (HttpResponse::HTTP_UNSUPPORTED_MEDIA_TYPE === $statusCode) {
                throw new Exception('Unsupported media Types.');
            }

            throw new Exception($e->getResponse()->getBody(), $statusCode);
        } catch (ConnectException $e) {
            throw new ConnectionException("Couldn't connect to server.");
        }
    }

    /**
     * Get X bytes of data from file.
     *
     * @param int $offset
     * @param int $bytes
     *
     * @return string
     */
    protected function getData(int $offset, int $bytes) : string
    {
        $file   = new File;
        $handle = $file->open($this->getFilePath(), $file::READ_BINARY);

        $file->seek($handle, $offset);

        $data = $file->read($handle, $bytes);

        $file->close($handle);

        return (string) $data;
    }

    /**
     * Get upload checksum header.
     *
     * @return string
     */
    protected function getUploadChecksumHeader() : string
    {
        return $this->getChecksumAlgorithm() . ' ' . base64_encode($this->getChecksum());
    }
}

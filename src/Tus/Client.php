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
     * @param string           $baseUrl
     * @param Cacheable|string $cacheAdapter
     */
    public function __construct($baseUrl, $cacheAdapter = 'file')
    {
    	$baseUrl = strval($baseUrl);
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
    public function file($file, $name = null)
    {
    	$file = strval($file);
    	$name = strval($name);
        $this->filePath = $file;

        if ( ! file_exists($file) || ! is_readable($file)) {
            throw new FileException('Cannot read file: ' . $file);
        }

        $this->fileName = $name === null ? basename($this->filePath) : $name;
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
    public function setFileName($name)
    {
        $this->fileName = strval($name);

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
     * @return int
     */
    public function getFileSize()
    {
        return intval($this->fileSize);
    }

    /**
     * Get guzzle client.
     *
     * @return GuzzleClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Get checksum.
     *
     * @return string
     */
    public function getChecksum()
    {
        if (empty($this->checksum)) {
            $this->checksum = hash_file($this->getChecksumAlgorithm(), $this->getFilePath());
        }

        return strval($this->checksum);
    }

    /**
     * Set key.
     *
     * @param string $key
     *
     * @return Client
     */
    public function setKey($key)
    {
        $this->key = strval($key);

        return $this;
    }

    /**
     * Get key.
     *
     * @return string
     */
    public function getKey()
    {
        return strval($this->key);
    }

    /**
     * Set checksum algorithm.
     *
     * @param string $algorithm
     *
     * @return Client
     */
    public function setChecksumAlgorithm($algorithm)
    {
    	$algorithm = strval($algorithm);
        $this->checksumAlgorithm = $algorithm;

        return $this;
    }

    /**
     * Get checksum algorithm.
     *
     * @return string
     */
    public function getChecksumAlgorithm()
    {
        return strval($this->checksumAlgorithm);
    }

    /**
     * Check if this is a partial upload request.
     *
     * @return bool
     */
    public function isPartial()
    {
        return boolval($this->partial);
    }

    /**
     * Get partial offset.
     *
     * @return int
     */
    public function getPartialOffset()
    {
        return intval($this->partialOffset);
    }

    /**
     * Set offset and force this to be a partial upload request.
     *
     * @param int $offset
     *
     * @return self
     */
    public function seek($offset)
    {
        $this->partialOffset = intval($offset);

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
    public function upload($bytes = -1)
    {
    	$bytes = intval($bytes);
        $bytes = $bytes < 0 ? $this->getFileSize() : $bytes;
        $key   = $this->getKey();

        try {
	        // Check if this upload exists with HEAD request.
	        $this->sendHeadRequest($key);
        } catch (FileException $e) {
        	$this->create($key);
        } catch (ClientException $e) {
            $this->create($key);
        } catch (ConnectException $e) {
            throw new ConnectionException("Couldn't connect to server.");
        }

        // Now, resume upload with PATCH request.
        return intval($this->sendPatchRequest($key, $bytes));
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
     * @param string $key
     *
     * @throws FileException
     *
     * @return void
     */
    public function create($key)
    {
    	$key = strval($key);
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
    public function concat($key, ...$partials)
    {
    	$key = strval($key);
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
        $checksum   = isset($data['data']['checksum']) ? $data['data']['checksum'] : null;
        $statusCode = $response->getStatusCode();

        if (HttpResponse::HTTP_CREATED !== $statusCode || ! $checksum) {
            throw new FileException('Unable to create resource.');
        }

        return strval($checksum);
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
    public function delete($key)
    {
    	$key = strval($key);
        try {
            $this->getClient()->delete($this->apiPath . '/' . $key, [
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
     *
     * @return void
     */
    protected function partial($state = true)
    {
    	$state = boolval($state);
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
    protected function sendHeadRequest($key)
    {
    	$key = strval($key);
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
     * @param string $key
     * @param int    $bytes
     *
     * @throws Exception
     * @throws FileException
     * @throws ConnectionException
     *
     * @return int
     */
    protected function sendPatchRequest($key, $bytes)
    {
    	$key = strval($key);
    	$bytes = intval($bytes);
        $data    = $this->getData($key, $bytes);
        $headers = [
            'Content-Type' => 'application/offset+octet-stream',
            'Content-Length' => strlen($data),
            'Upload-Checksum' => $this->getUploadChecksumHeader(),
        ];

        if ($this->isPartial()) {
            $headers += ['Upload-Concat' => self::UPLOAD_TYPE_PARTIAL];
        }

        try {
            $response = $this->getClient()->patch($this->apiPath . '/' . $key, [
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
     * @param string $key
     * @param int    $bytes
     *
     * @return string
     */
    protected function getData($key, $bytes)
    {
    	$key = strval($key);
    	$bytes = intval($bytes);
        $file   = new File;
        $handle = $file->open($this->getFilePath(), $file::READ_BINARY);
        $offset = $this->partialOffset;

        if ($offset < 0) {
            $fileMeta = $this->getCache()->get($key);
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
     * @return string
     */
    protected function getUploadChecksumHeader()
    {
        return $this->getChecksumAlgorithm() . ' ' . base64_encode($this->getChecksum());
    }
}

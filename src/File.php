<?php

namespace TusPhp;

use Carbon\Carbon;
use TusPhp\Cache\Cacheable;
use TusPhp\Exception\FileException;
use TusPhp\Exception\ConnectionException;
use TusPhp\Exception\OutOfRangeException;

class File
{
    /** @const Max chunk size */
    const CHUNK_SIZE = 8192; // 8 bytes.

    /** @const Input stream */
    const INPUT_STREAM = 'php://input';

    /** @const Read binary mode */
    const READ_BINARY = 'rb';

    /** @const Append binary mode */
    const APPEND_BINARY = 'ab+';

    /** @var string */
    protected $key;

    /** @var string */
    protected $checksum;

    /** @var string */
    protected $name;

    /** @var Cacheable */
    protected $cache;

    /** @var int */
    protected $offset;

    /** @var string */
    protected $location;

    /** @var string */
    protected $filePath;

    /** @var int */
    protected $fileSize;

    /**
     * File constructor.
     *
     * @param string|null    $name
     * @param Cacheable|null $cache
     */
    public function __construct($name = null, Cacheable $cache = null)
    {
        $this->name  = $name === null ? null : strval($name);
        $this->cache = $cache;
    }

    /**
     * Set file meta.
     *
     * @param int    $offset
     * @param int    $fileSize
     * @param string $filePath
     * @param string $location
     *
     * @return File
     */
    public function setMeta($offset, $fileSize, $filePath, $location = null)
    {
        $this->offset   = intval($offset);
        $this->fileSize = intval($fileSize);
        $this->filePath = strval($filePath);
        $this->location = strval($location);

        return $this;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return File
     */
    public function setName($name)
    {
        $this->name = strval($name);

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return strval($this->name);
    }

    /**
     * Set file size.
     *
     * @param int $size
     *
     * @return File
     */
    public function setFileSize($size)
    {
        $this->fileSize = intval($size);

        return $this;
    }

    /**
     * Get file size.
     *
     * @return int
     */
    public function getFileSize()
    {
        return $this->fileSize;
    }

    /**
     * Set key.
     *
     * @param string $key
     *
     * @return File
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
        return $this->key;
    }

    /**
     * Set checksum.
     *
     * @param string $checksum
     *
     * @return File
     */
    public function setChecksum($checksum)
    {
        $this->checksum = strval($checksum);

        return $this;
    }

    /**
     * Get checksum.
     *
     * @return string
     */
    public function getChecksum()
    {
        return strval($this->checksum);
    }

    /**
     * Set offset.
     *
     * @param int $offset
     *
     * @return File
     */
    public function setOffset($offset)
    {
        $this->offset = intval($offset);

        return $this;
    }

    /**
     * Get offset.
     *
     * @return int
     */
    public function getOffset()
    {
        return intval($this->offset);
    }

    /**
     * Set location.
     *
     * @param string $location
     *
     * @return File
     */
    public function setLocation($location)
    {
        $this->location = strval($location);

        return $this;
    }

    /**
     * Get location.
     *
     * @return string
     */
    public function getLocation()
    {
        return strval($this->location);
    }

    /**
     * Set absolute file location.
     *
     * @param string $path
     *
     * @return File
     */
    public function setFilePath($path)
    {
        $this->filePath = strval($path);

        return $this;
    }

    /**
     * Get absolute location.
     *
     * @return string
     */
    public function getFilePath()
    {
        return strval($this->filePath);
    }

    /**
     * Get input stream.
     *
     * @return string
     */
    public function getInputStream()
    {
        return strval(self::INPUT_STREAM);
    }

    /**
     * Get file meta.
     *
     * @return array
     */
    public function details()
    {
        $now = Carbon::now();
		$cache = $this->cache;

        return [
            'name' => $this->name,
            'size' => $this->fileSize,
            'offset' => $this->offset,
            'checksum' => $this->checksum,
            'location' => $this->location,
            'file_path' => $this->filePath,
            'created_at' => $now->format($cache::RFC_7231),
            'expires_at' => $now->addSeconds($this->cache->getTtl())->format($cache::RFC_7231),
        ];
    }

    /**
     * Upload file to server.
     *
     * @param int $totalBytes
     *
     * @throws ConnectionException
     *
     * @return int
     */
    public function upload($totalBytes)
    {
    	$totalBytes = intval($totalBytes);
        $bytesWritten = $this->offset;

        if ($bytesWritten === $totalBytes) {
            return $bytesWritten;
        }

        $input  = $this->open($this->getInputStream(), self::READ_BINARY);
        $output = $this->open($this->getFilePath(), self::APPEND_BINARY);
        $key    = $this->getKey();

        try {
            $this->seek($output, $bytesWritten);

            while ( ! feof($input)) {
                if (CONNECTION_NORMAL !== connection_status()) {
                    throw new ConnectionException('Connection aborted by user.');
                }

                $data  = $this->read($input, self::CHUNK_SIZE);
                $bytes = $this->write($output, $data, self::CHUNK_SIZE);

                $bytesWritten += $bytes;

                $this->cache->set($key, ['offset' => $bytesWritten]);

                if ($bytesWritten > $totalBytes) {
                    throw new OutOfRangeException('The uploaded file is corrupt.');
                }

                if ($bytesWritten === $totalBytes) {
                    break;
                }
            }
        } finally {
            $this->close($input);
            $this->close($output);
        }

        return $bytesWritten;
    }

    /**
     * Open file in append binary mode.
     *
     * @param string $filePath
     * @param string $mode
     *
     * @throws FileException
     *
     * @return resource
     */
    public function open($filePath, $mode)
    {
    	$filePath = strval($filePath);
    	$mode = strval($mode);
        $this->exists($filePath, $mode);

        $ptr = @fopen($filePath, $mode);

        if (false === $ptr) {
            throw new FileException("Unable to open $filePath.");
        }

        return $ptr;
    }

    /**
     * Check if file to read exists.
     *
     * @param string $filePath
     * @param string $mode
     *
     * @throws FileException
     *
     * @return bool
     */
    public function exists($filePath, $mode = self::READ_BINARY)
    {
    	$filePath = strval($filePath);
    	$mode = strval($mode);
        if (self::INPUT_STREAM === $filePath) {
            return true;
        }

        if (self::READ_BINARY === $mode && ! file_exists($filePath)) {
            throw new FileException('File not found.');
        }

        return true;
    }

    /**
     * @param Resource $handle
     * @param int      $offset
     * @param int      $whence
     *
     * @throws FileException
     *
     * @return int
     */
    public function seek($handle, $offset, $whence = SEEK_SET)
    {
    	$offset = intval($offset);
    	$whence = intval($whence);
        $position = fseek($handle, $offset, $whence);

        if (-1 === $position) {
            throw new FileException('Cannot move pointer to desired position.');
        }

        return $position;
    }

    /**
     * Read data from file.
     *
     * @param Resource $handle
     * @param int      $chunkSize
     *
     * @throws FileException
     *
     * @return string
     */
    public function read($handle, $chunkSize)
    {
    	$chunkSize = intval($chunkSize);

        $data = fread($handle, $chunkSize);

        if (false === $data) {
            throw new FileException('Cannot read file.');
        }

        return (string) $data;
    }

    /**
     * Write data to file.
     *
     * @param Resource $handle
     * @param string   $data
     * @param int|null $length
     *
     * @throws FileException
     *
     * @return int
     */
    public function write($handle, $data, $length = null)
    {
    	$data = strval($data);
        $bytesWritten = is_int($length) ? fwrite($handle, $data, $length) : fwrite($handle, $data);

        if (false === $bytesWritten) {
            throw new FileException('Cannot write to a file.');
        }

        return $bytesWritten;
    }

    /**
     * Merge 2 or more files.
     *
     * @param array $files File data with meta info.
     *
     * @return int
     */
    public function merge(array $files)
    {
        $destination = $this->getFilePath();
        $firstFile   = array_shift($files);

        // First partial file can directly be copied.
        $this->copy($firstFile['file_path'], $destination);

        $this->offset   = $firstFile['offset'];
        $this->fileSize = filesize($firstFile['file_path']);

        $handle = $this->open($destination, self::APPEND_BINARY);

        foreach ($files as $file) {
            if ( ! file_exists($file['file_path'])) {
                throw new FileException('File to be merged not found.');
            }

            $this->fileSize += $this->write($handle, file_get_contents($file['file_path']));

            $this->offset += $file['offset'];
        }

        $this->close($handle);

        return $this->fileSize;
    }

    /**
     * Copy file from source to destination.
     *
     * @param string $source
     * @param string $destination
     *
     * @return bool
     */
    public function copy($source, $destination)
    {
    	$source = strval($source);
    	$destination = strval($destination);
        $status = @copy($source, $destination);

        if (false === $status) {
            throw new FileException('Cannot copy source to destination.');
        }

        return $status;
    }

    /**
     * Delete file and/or folder.
     *
     * @param array $files
     * @param bool  $folder
     *
     * @return bool
     */
    public function delete(array $files, $folder = false)
    {
    	$folder = boolval($folder);
        $status = $this->deleteFiles($files);

        if ($status && $folder) {
            return rmdir(dirname(current($files)));
        }

        return $status;
    }

    /**
     * Delete files.
     *
     * @param array $files
     *
     * @return bool
     */
    public function deleteFiles(array $files)
    {
        if (empty($files)) {
            return false;
        }

        $status = true;

        foreach ($files as $file) {
            if (file_exists($file)) {
                $status = $status && unlink($file);
            }
        }

        return $status;
    }

    /**
     * Close file.
     *
     * @param $handle
     *
     * @return bool
     */
    public function close($handle)
    {
        return fclose($handle);
    }
}

<?php

namespace TusPhp\Cache;

use Carbon\Carbon;
use TusPhp\Config;

class FileStore extends AbstractCache
{
    /** @var string */
    protected $cacheDir;

    /** @var string */
    protected $cacheFile;

    /**
     * FileStore constructor.
     *
     * @param string|null $cacheDir
     * @param string|null $cacheFile
     */
    public function __construct(string $cacheDir = null, string $cacheFile = null)
    {
        $cacheDir  = $cacheDir ?? Config::get('file.dir');
        $cacheFile = $cacheFile ?? Config::get('file.name');

        $this->setCacheDir($cacheDir);
        $this->setCacheFile($cacheFile);
    }

    /**
     * Set cache dir.
     *
     * @param string $path
     *
     * @return self
     */
    public function setCacheDir(string $path): self
    {
        $this->cacheDir = $path;

        return $this;
    }

    /**
     * Get cache dir.
     *
     * @return string
     */
    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    /**
     * Set cache file.
     *
     * @param string $file
     *
     * @return self
     */
    public function setCacheFile(string $file): self
    {
        $this->cacheFile = $file;

        return $this;
    }

    /**
     * Get cache file.
     *
     * @return string
     */
    public function getCacheFile(): string
    {
        return $this->cacheDir . $this->cacheFile;
    }

    /**
     * Create cache dir if not exists.
     *
     * @return void
     */
    protected function createCacheDir()
    {
        if ( ! file_exists($this->cacheDir)) {
            mkdir($this->cacheDir);
        }
    }

    /**
     * Create a cache file.
     *
     * @return void
     */
    protected function createCacheFile()
    {
        $this->createCacheDir();

        $cacheFilePath = $this->getCacheFile();

        if ( ! file_exists($cacheFilePath)) {
            touch($cacheFilePath);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, bool $withExpired = false)
    {
        $key = $this->getActualCacheKey($key);

        return $this->lockedGet(
            $this->getCacheFile(),
            false,
            function ($handler, array $content) use ($withExpired, $key) {
                if (empty($content[$key])) {
                    return null;
                }

                $meta = $content[$key];

                if ( ! $withExpired && ! $this->isValid($meta)) {
                    return null;
                }

                return $meta;
            }
        );
    }

    /**
     * Write the contents of a file with exclusive lock.
     *
     * It is not recommended to use this method, for updating files as it will nullify any changes that have been made
     * to the file between retrieving $contents and writing the changes. As such, one should instead use lockedSet.
     *
     * @param string $path
     * @param string $contents
     *
     * @return int|bool The amount of bytes that were written, or false if the write failed
     * @see LockedFileStore::lockedSet
     *
     * @deprecated It is not recommended to use this method, use `lockedSet` instead.
     */
    public function put(string $path, string $contents)
    {
        return $this->lockedSet(
            $path,
            function () use ($contents) {
                return $contents;
            }
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return int|bool The amount of bytes that were written, or false if the write failed
     */
    public function set(string $key, $value)
    {
        $cacheKey  = $this->getActualCacheKey($key);
        $cacheFile = $this->getCacheFile();

        if ( ! file_exists($cacheFile)) {
            $this->createCacheFile();
        }

        return $this->lockedSet($cacheFile, function (array $data) use ($value, $cacheKey) {
            if ( ! empty($data[$cacheKey]) && \is_array($value)) {
                $data[$cacheKey] = $value + $data[$cacheKey];
            } else {
                $data[$cacheKey] = $value;
            }

            return $data;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function delete(string $key): bool
    {
        $cacheKey = $this->getActualCacheKey($key);
        $deletion = false;

        return $this->lockedSet(
            $this->getCacheFile(),
            function ($data) use ($cacheKey, &$deletion) {
                if (isset($data[$cacheKey])) {
                    unset($data[$cacheKey]);
                    $deletion = true;
                }

                return $data;
            }
        ) !== false && $deletion;
    }

    /**
     * {@inheritDoc}
     */
    public function keys() : array
    {
        $contents = $this->getCacheContents();

        if (\is_array($contents)) {
            return array_keys($contents);
        }

        return [];
    }

    /**
     * Check if cache is still valid.
     *
     * @param string|array $meta The cache key, or the metadata object
     * @return bool
     */
    public function isValid($meta): bool
    {
        if ( ! \is_array($meta)) {
            $key  = $this->getActualCacheKey($meta);
            $meta = $this->lockedGet($this->getCacheFile())[$key] ?? [];
        }

        if (empty($meta['expires_at'])) {
            return false;
        }

        return Carbon::now() < Carbon::createFromFormat(self::RFC_7231, $meta['expires_at']);
    }

    /**
     * Get cache contents.
     *
     * @return array|bool
     */
    public function getCacheContents()
    {
        $cacheFile = $this->getCacheFile();

        if ( ! file_exists($cacheFile)) {
            return false;
        }

        return $this->lockedGet($cacheFile);
    }

    /**
     * Get actual cache key with prefix.
     *
     * @param string $key
     *
     * @return string
     */
    public function getActualCacheKey(string $key): string
    {
        $prefix = $this->getPrefix();

        if (false === strpos($key, $prefix)) {
            $key = $prefix . $key;
        }

        return $key;
    }

    //region File lock related operations

    /**
     * Acquire a lock on the given handle
     *
     * @param resource $handle
     * @param int $lock The lock operation (`LOCK_SH`, `LOCK_EX`, or `LOCK_UN`)
     * @param int $attempts The number of attempts before returning false
     * @return bool True if the lock operation was successful
     *
     * @see LOCK_SH
     * @see LOCK_EX
     * @see LOCK_UN
     */
    public function acquireLock($handle, int $lock, int $attempts = 5): bool
    {
        for ($i = 0; $i < $attempts; $i++) {
            if (flock($handle, $lock)) {
                return true;
            }

            usleep(100);
        }

        return false;
    }

    /**
     * Get contents of a file with shared access.
     *
     * Example of the callable:
     * ```php
     * $property = function($handler, array $data) {
     *      return $data['someProperty'];
     * }
     *
     * // $property now has the contents of $data['someProperty']
     * ```
     *
     * @param string $path
     * @param bool $exclusive Whether we should use an exclusive or shared lock. If you are writing to the file, an
     *  exclusive lock is recommended
     * @param callable|null $callback The callable to call when we have locked the file. The first argument is the
     *  handle, the second is an array with the cache contents. If left empty, a default callable will be used that
     *  returns the data of the file. The callable will not be called if reading the cache fails.
     * @return mixed The output of the callable
     */
    public function lockedGet(string $path, bool $exclusive = false, ?callable $callback = null)
    {
        if ($callback === null) {
            $callback = function ($handler, $data) {
                return $data;
            };
        }

        if ( ! file_exists($path)) {
            $this->createCacheFile();
        }

        $handle = @fopen($path, 'r+b');
        $lock   = $exclusive ? LOCK_EX : LOCK_SH;

        if ($handle === false || ! $this->acquireLock($handle, $lock)) {
            return null;
        }

        try {
            clearstatcache(true, $path);

            $contents = fread($handle, filesize($path) ?: 1);

            // Read the JSON data
            $data = @json_decode($contents, true) ?? [];

            return $callback($handle, $data);
        } finally {
            $this->acquireLock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Write contents to the given path while locking the file
     *
     * This locks the file during the callback: no other instances can read/modify the file while this operation is
     * taking place. As such, one should use the data provided as an argument in the callback for modifying the file
     * to prevent the loss of data.
     *
     * Example of the callable:
     * ```php
     * function(array $data) {
     *     $data['someProperty'] = true;
     *
     *     return $data;
     * }
     * ```
     *
     * @param string $path The path of the file to write to
     * @param callable $callback A callable for transforming the data. The first argument of the callable is the current
     *  file contents, the return value will be the new contents (which will be json encoded if it is not a string
     *  already)
     * @return int|bool The amount of bytes that were written, or false if the write failed
     */
    public function lockedSet(string $path, callable $callback)
    {
        $output = $this->lockedGet($path, true, function ($handle, array $data) use ($callback) {
            $data = $callback($data) ?? [];

            ftruncate($handle, 0);
            rewind($handle);

            $data = \is_string($data) ? $data : json_encode($data);
            $write = fwrite($handle, $data);

            fflush($handle);

            return $write;
        });

        return $output === null ? false : $output;
    }
    //endregion
}

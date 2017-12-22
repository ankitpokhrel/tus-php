<?php

namespace TusPhp\Cache;

use Carbon\Carbon;

class FileStore extends AbstractCache
{
    /** @var string */
    protected $cacheDir;

    /** @var string */
    protected $cacheFile;

    /**
     * FileStore constructor.
     */
    public function __construct()
    {
        $this->setCacheDir(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.cache' . DIRECTORY_SEPARATOR);
        $this->setCacheFile('tus_php.cache');
    }

    /**
     * Set cache dir.
     *
     * @param string $path
     *
     * @return self
     */
    public function setCacheDir(string $path) : self
    {
        $this->cacheDir = $path;

        return $this;
    }

    /**
     * Get cache dir.
     *
     * @return string
     */
    public function getCacheDir() : string
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
    public function setCacheFile(string $file) : self
    {
        $this->cacheFile = $file;

        return $this;
    }

    /**
     * Get cache file.
     *
     * @return string
     */
    public function getCacheFile() : string
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
     * Create cache file and add required meta.
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
     * {@inheritdoc}
     */
    public function get(string $key)
    {
        $contents = $this->getCacheContents();

        if ( ! empty($contents[$key]) && $this->isValid($key)) {
            return $contents[$key];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key)
    {
        $contents = $this->getCacheContents();

        if (isset($contents[$key])) {
            unset($contents[$key]);

            return false !== file_put_contents($this->getCacheFile(), json_encode($contents));
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value)
    {
        $cacheFile = $this->getCacheFile();

        if ( ! file_exists($cacheFile) || ! $this->isValid($key)) {
            $this->createCacheFile();
        }

        $contents = json_decode(file_get_contents($cacheFile), true) ?? [];

        if ( ! empty($contents[$key]) && is_array($value)) {
            $contents[$key] = $value + $contents[$key];
        } else {
            $contents[$key] = $value;
        }

        return file_put_contents($cacheFile, json_encode($contents));
    }

    /**
     * Check if cache is still valid.
     *
     * @param string $key
     *
     * @return bool
     */
    public function isValid(string $key) : bool
    {
        $meta = $this->getCacheContents()[$key] ?? [];

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

        return json_decode(file_get_contents($cacheFile), true) ?? [];
    }
}

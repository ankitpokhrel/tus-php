<?php

namespace TusPhp\Cache;

use Carbon\Carbon;
use TusPhp\Config;
use TusPhp\Json;

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
    public function __construct($cacheDir = null, $cacheFile = null)
    {
        $cacheDir  = $cacheDir === null ? Config::get('file.dir') : strval($cacheDir);
        $cacheFile = $cacheFile === null ? Config::get('file.name') : strval($cacheFile);

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
    public function setCacheDir($path)
    {
        $this->cacheDir = strval($path);

        return $this;
    }

    /**
     * Get cache dir.
     *
     * @return string
     */
    public function getCacheDir()
    {
        return strval($this->cacheDir);
    }

    /**
     * Set cache file.
     *
     * @param string $file
     *
     * @return self
     */
    public function setCacheFile($file)
    {
        $this->cacheFile = strval($file);

        return $this;
    }

    /**
     * Get cache file.
     *
     * @return string
     */
    public function getCacheFile()
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
     * {@inheritDoc}
     */
    public function get($key, $withExpired = false)
    {
    	$key = strval($key);
    	$withExpired = boolval($withExpired);

        $key      = $this->getActualCacheKey($key);
        $contents = $this->getCacheContents();

        if (empty($contents[$key])) {
            return null;
        }

        if ($withExpired) {
            return $contents[$key];
        }

        return $this->isValid($key) ? $contents[$key] : null;
    }

    /**
     * {@inheritDoc}
     */
    public function set($key, $value)
    {
    	$key = strval($key);

        $key       = $this->getActualCacheKey($key);
        $cacheFile = $this->getCacheFile();

        if ( ! file_exists($cacheFile) || ! $this->isValid($key)) {
            $this->createCacheFile();
        }

        $contents = Json::decodeOrEmptyArray(file_get_contents($cacheFile));

        if ( ! empty($contents[$key]) && is_array($value)) {
            $contents[$key] = $value + $contents[$key];
        } else {
            $contents[$key] = $value;
        }

        return file_put_contents($cacheFile, json_encode($contents));
    }

    /**
     * {@inheritDoc}
     */
    public function delete($key)
    {
    	$key = strval($key);

        $key      = $this->getActualCacheKey($key);
        $contents = $this->getCacheContents();

        if (isset($contents[$key])) {
            unset($contents[$key]);

            return false !== file_put_contents($this->getCacheFile(), json_encode($contents));
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function keys()
    {
        $contents = $this->getCacheContents();

        if (is_array($contents)) {
            return array_keys($this->getCacheContents());
        }

        return [];
    }

    /**
     * Check if cache is still valid.
     *
     * @param string $key
     *
     * @return bool
     */
    public function isValid($key)
    {
    	$key = strval($key);

        $key  = $this->getActualCacheKey($key);
	    $contents = $this->getCacheContents()[$key];
	    $meta = $contents === null ? [] : $contents;

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

	    return Json::decodeOrEmptyArray(file_get_contents($cacheFile));
    }

    /**
     * Get actual cache key with prefix.
     *
     * @param string $key
     *
     * @return string
     */
    public function getActualCacheKey($key)
    {
    	$key = strval($key);
        $prefix = $this->getPrefix();

        if (false === strpos($key, $prefix)) {
            $key = $prefix . $key;
        }

        return $key;
    }
}

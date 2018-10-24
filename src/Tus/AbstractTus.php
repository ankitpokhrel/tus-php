<?php

namespace TusPhp\Tus;

use TusPhp\Cache\Cacheable;
use TusPhp\Cache\CacheFactory;

abstract class AbstractTus
{
    /** @const string Tus protocol version. */
    const TUS_PROTOCOL_VERSION = '1.0.0';

    /** @const string Name separator for partial upload. */
    const PARTIAL_UPLOAD_NAME_SEPARATOR = '_';

    /** @const string Upload type normal. */
    const UPLOAD_TYPE_NORMAL = 'normal';

    /** @const string Upload type partial. */
    const UPLOAD_TYPE_PARTIAL = 'partial';

    /** @const string Upload type final. */
    const UPLOAD_TYPE_FINAL = 'final';

    /** @const string Header Content Type */
    const HEADER_CONTENT_TYPE = 'application/offset+octet-stream';

    /** @var Cacheable */
    protected $cache;

    /** @var string */
    protected $apiPath = '/files';

    /**
     * Set cache.
     *
     * @param mixed $cache
     *
     * @return self
     */
    public function setCache($cache) : self
    {
        if (is_string($cache)) {
            $this->cache = CacheFactory::make($cache);
        } elseif ($cache instanceof Cacheable) {
            $this->cache = $cache;
        }

        return $this;
    }

    /**
     * Get cache.
     *
     * @return Cacheable
     */
    public function getCache() : Cacheable
    {
        return $this->cache;
    }

    /**
     * Set API path.
     *
     * @param string $path
     *
     * @return self
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
}

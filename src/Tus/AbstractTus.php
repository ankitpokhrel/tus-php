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

    /** @var Cacheable */
    protected $cache;

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
}

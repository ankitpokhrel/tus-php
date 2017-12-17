<?php

namespace TusPhp\Cache;

class CacheFactory
{
    /**
     * Make cache.
     *
     * @param string $type
     *
     * @static
     *
     * @return Cacheable
     */
    public static function make(string $type = 'file') : Cacheable
    {
        switch ($type) {
            case 'redis':
                return new RedisStore;
        }

        return new FileStore;
    }
}

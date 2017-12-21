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
                $redisHost = getenv('REDIS_HOST');
                $redisPort = getenv('REDIS_PORT');

                return new RedisStore([
                    'host' => $redisHost ? $redisHost : '127.0.0.1',
                    'port' => $redisPort ? $redisPort : '6379',
                ]);
        }

        return new FileStore;
    }
}

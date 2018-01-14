<?php

namespace TusPhp\Cache;

use Predis\Client as RedisClient;

class RedisStore extends AbstractCache
{
    /** @const Tus Redis Prefix */
    const TUS_REDIS_PREFIX = 'tus:';

    /** @var RedisClient */
    protected $redis;

    /**
     * RedisStore constructor.
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->redis = new RedisClient($options);
    }

    /**
     * Get redis.
     *
     * @return RedisClient
     */
    public function getRedis() : RedisClient
    {
        return $this->redis;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key)
    {
        $contents = $this->redis->get(self::TUS_REDIS_PREFIX . $key);

        if ( ! empty($contents)) {
            return json_decode($contents, true);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value)
    {
        $contents = $this->get($key) ?? [];

        if (is_array($value)) {
            $contents = $value + $contents;
        } else {
            array_push($contents, $value);
        }

        $ttl = $this->getRedis()->ttl($key);
        if ($ttl <= 0) {
            $ttl = $this->getTtl();
        }

        $status = $this->redis->setex(self::TUS_REDIS_PREFIX . $key, $ttl, json_encode($contents));

        return 'OK' === $status->getPayload();
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key)
    {
        return $this->redis->del(self::TUS_REDIS_PREFIX . $key) > 0;
    }
}

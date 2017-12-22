<?php

namespace TusPhp\Cache;

use Predis\Client as RedisClient;

class RedisStore extends AbstractCache
{
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
     * {@inheritdoc}
     */
    public function get(string $key)
    {
        $contents = $this->redis->get($key);

        if ( ! empty($contents)) {
            return json_decode($contents, true);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key)
    {
        return $this->redis->del($key) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value)
    {
        $contents = json_decode($this->redis->get($key), true) ?? [];

        if (is_array($value)) {
            $contents = $value + $contents;
        } else {
            array_push($contents, $value);
        }

        $ttl = $this->getRedis()->ttl($key);
        if ($ttl <= 0) {
            $ttl = $this->getTtl();
        }

        $status = $this->redis->setex($key, $ttl, json_encode($contents));

        return 'OK' === $status->getPayload();
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
}

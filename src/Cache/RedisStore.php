<?php

namespace TusPhp\Cache;

use Carbon\Carbon;
use Predis\Client as RedisClient;

class RedisStore extends AbstractCache
{
    /** @const string Prefix for redis keys */
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
            $contents = json_decode($contents, true);

            $isExpired = Carbon::parse($contents['expires_at'])->lt(Carbon::now());

            return $isExpired ? null : $contents;
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

        $status = $this->redis->set(self::TUS_REDIS_PREFIX . $key, json_encode($contents));

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

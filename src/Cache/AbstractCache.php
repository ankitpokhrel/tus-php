<?php

namespace TusPhp\Cache;

abstract class AbstractCache implements Cacheable
{
    /** @var int TTL in secs (default 1 day) */
    protected $ttl = 86400;

    /**
     * Set time to live.
     *
     * @param int $secs
     */
    public function setTtl(int $secs)
    {
        $this->ttl = $secs;
    }

    /**
     * {@inheritDoc}
     */
    public function getTtl() : int
    {
        return $this->ttl;
    }

    /**
     * Delete all keys.
     *
     * @param array $keys
     *
     * @return bool
     */
    public function deleteAll(array $keys) : bool
    {
        if (empty($keys)) {
            return false;
        }

        $status = true;

        foreach ($keys as $key) {
            $status = $status && $this->delete($key);
        }

        return $status;
    }
}

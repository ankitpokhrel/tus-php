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
     * {@inheritdoc}
     */
    public function getTtl() : int
    {
        return $this->ttl;
    }
}

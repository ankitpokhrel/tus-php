<?php

namespace TusPhp\Cache;

interface Cacheable
{
    /** @see https://tools.ietf.org/html/rfc7231#section-7.1.1.1 */
    const RFC_7231 = 'D, d M Y H:i:s \G\M\T';

    /**
     * Get data associated with the key.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $key);

    /**
     * Set data to the given key.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return mixed
     */
    public function set(string $key, $value);

    /**
     * Delete data associated with the key.
     *
     * @param string $key
     *
     * @return bool
     */
    public function delete(string $key);

    /**
     * Get time to live.
     *
     * @return int
     */
    public function getTtl() : int;
}

<?php

namespace TusPhp;

class Config
{
    /** @const string */
    const DEFAULT_CONFIG_PATH = __DIR__ . '/Config/default.php';

    /** @var array */
    protected static $config = [];

    /**
     * Load default application configs.
     *
     * @param string|array $config
     * @param bool         $force
     *
     * @return void
     */
    public static function setConfig($config = null, bool $force = false)
    {
        if ( ! $force && ! empty(self::$config)) {
            return;
        }

        if (is_array($config)) {
            self::$config = $config;
        } elseif (is_string($config)) {
            self::$config = require $config ?? self::DEFAULT_CONFIG_PATH;
        }
    }

    /**
     * Get config.
     *
     * @param string|null $key Key to extract.
     *
     * @return mixed
     */
    public static function get(string $key = null)
    {
        if (empty($key)) {
            return self::$config;
        }

        $keys  = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $key) {
            if ( ! isset($value[$key])) {
                return null;
            }

            $value = $value[$key];
        }

        return $value;
    }
}

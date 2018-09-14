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
    public static function set($config = null, $force = false)
    {
        if ( ! $force && ! empty(self::$config)) {
            return;
        }

        if (is_array($config)) {
            self::$config = $config;
        } else {
            self::$config = require $config;
            if (self::$config === null) {
	            self::$config = self::DEFAULT_CONFIG_PATH;
            }
        }
    }

    /**
     * Get config.
     *
     * @param string|null $key Key to extract.
     *
     * @return mixed
     */
    public static function get($key = null)
    {
        self::set();

        if (empty($key)) {
            return self::$config;
        }
        $key = strval($key);

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

<?php

namespace TusPhp;

class Config
{
    /** @const string */
    const DEFAULT_CONFIG_PATH = __DIR__ . '/config/default.php';

    /** @var array */
    protected static $config = [];

    /**
     * Load default application configs.
     *
     * @param string|null $path
     *
     * @return void
     */
    public static function setConfig($path = null)
    {
        if (empty(self::$config)) {
            self::$config = require $path ?? self::DEFAULT_CONFIG_PATH;
        }
    }

    /**
     * Get config.
     *
     * @param string|null $key  Key to extract.
     *
     * @return mixed
     */
    public static function get(string $key = null)
    {
        self::setConfig();

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

<?php

namespace TusPhp\Test;

use TusPhp\Config;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \TusPhp\Config
 */
class ConfigTest extends TestCase
{
    /** @var array */
    protected $config;

    /**
     * Prepare vars.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->config = require __DIR__ . '/Fixtures/config.php';
    }

    /**
     * @test
     *
     * @covers ::set
     * @covers ::get
     */
    public function it_loads_config_from_array()
    {
        $config = [
            'redis' => [
                'host' => '129.0.0.1',
                'port' => '6381',
                'database' => 15,
            ],
            'file' => [
                'dir' => '/var/www/.cache/',
                'name' => 'tus_php.cache',
            ],
        ];

        Config::set($config, true);

        $this->assertEquals($config, Config::get());
    }

    /**
     * @test
     *
     * @covers ::set
     * @covers ::get
     */
    public function it_loads_config_from_file()
    {
        Config::set(__DIR__ . '/Fixtures/config.php', true);

        $this->assertEquals($this->config, Config::get());
    }

    /**
     * @test
     *
     * @covers ::set
     * @covers ::get
     */
    public function it_should_not_load_config_if_config_is_set()
    {
        Config::set([]);

        $this->assertNotEmpty(Config::get());
        $this->assertEquals($this->config, Config::get());
    }

    /**
     * @test
     *
     * @covers ::get
     */
    public function it_gets_value_for_a_key()
    {
        $this->assertEquals($this->config['redis'], Config::get('redis'));
        $this->assertEquals($this->config['redis']['host'], Config::get('redis.host'));
        $this->assertEquals($this->config['file']['meta']['name'], Config::get('file.meta.name'));
    }

    /**
     * @test
     *
     * @covers ::get
     */
    public function it_returns_null_for_invalid_key()
    {
        $this->assertNull(Config::get('redis.invalid'));
    }
}

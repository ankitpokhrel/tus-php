<?php

namespace TusPhp\Test\Cache;

use Exception;
use Predis\Client;
use TusPhp\Cache\RedisStore;
use PHPUnit\Framework\TestCase;
use Predis\Connection\ConnectionException;

/**
 * @coversDefaultClass \TusPhp\Cache\RedisStore
 */
class RedisStoreTest extends TestCase
{
    /** @var string */
    protected $checksum;

    /** @var RedisStore */
    protected $redisStore;

    /** @var Client|bool */
    protected static $connection;

    /**
     * Check redis connection.
     *
     * @return void
     */
    public static function setUpBeforeClass()
    {
        $connection = new Client([
            'host' => getenv('REDIS_HOST'),
            'port' => getenv('REDIS_PORT'),
            'timeout' => getenv('REDIS_TIMEOUT'),
            'database' => getenv('REDIS_DATABASE'),
        ]);

        try {
            $connection->ping();

            static::$connection = $connection;
        } catch (ConnectionException $e) {
            static::$connection = false;
        }

        parent::setUpBeforeClass();
    }

    /**
     * Prepare vars.
     *
     * @return void
     */
    protected function setUp()
    {
        if (false === static::$connection) {
            $this->markTestSkipped('Unable to connect to redis.');
        }

        $this->checksum   = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $this->redisStore = new RedisStore();

        parent::setUp();
    }

    /**
     * @test
     *
     * @covers ::__construct
     * @covers ::getRedis
     */
    public function it_passes_proper_options_to_redis_client()
    {
        $options = [
            'host' => '129.0.0.1',
            'port' => 6379,
            'timeout' => 0.5,
        ];

        $redisStore = new RedisStore($options);

        try {
            $redisStore->getRedis()->ping();
        } catch (Exception $e) {
            $this->assertTrue($e instanceof ConnectionException);
        }
    }

    /**
     * @test
     *
     * @covers ::set
     * @covers ::get
     * @covers ::setTtl
     */
    public function it_sets_and_gets_cache_contents()
    {
        $cacheContent = ['expires_at' => 'Sat, 09 Dec 2017 16:25:51 GMT', 'offset' => 100];

        $this->redisStore->setTtl(1);
        $this->redisStore->set($this->checksum, $cacheContent);

        $this->assertEquals($cacheContent, $this->redisStore->get($this->checksum));
    }

    /**
     * @test
     *
     * @covers ::set
     * @covers ::get
     *
     * @depends it_sets_and_gets_cache_contents
     */
    public function it_doesnt_replace_cache_key_in_set()
    {
        $this->redisStore->set($this->checksum, ['offset' => 500]);

        $contents = $this->redisStore->get($this->checksum);

        $this->assertEquals(500, $contents['offset']);
        $this->assertEquals('Sat, 09 Dec 2017 16:25:51 GMT', $contents['expires_at']);
    }

    /**
     * @test
     *
     * @covers ::get
     *
     * @depends it_sets_and_gets_cache_contents
     */
    public function it_returns_null_if_cache_is_expired()
    {
        sleep(1);

        $this->assertNull($this->redisStore->get($this->checksum));
    }

    /**
     * @test
     *
     * @covers ::set
     * @covers ::get
     * @covers ::delete
     */
    public function it_deletes_cache_content()
    {
        $cacheContent = ['expires_at' => 'Fri, 08 Dec 2017 16:25:51 GMT', 'offset' => 100];

        $this->redisStore->set($this->checksum, $cacheContent);

        $this->assertEquals($cacheContent, $this->redisStore->get($this->checksum));

        $this->assertFalse($this->redisStore->delete('invalid-checksum'));
        $this->assertTrue($this->redisStore->delete($this->checksum));
        $this->assertNull($this->redisStore->get($this->checksum));
    }

    /**
     * @test
     *
     * @covers ::getRedis
     */
    public function it_gets_redis_object()
    {
        $this->assertInstanceOf(Client::class, $this->redisStore->getRedis());
    }

    /**
     * Flush redis cache.
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        if (static::$connection) {
            static::$connection->flushall();
        }

        parent::tearDownAfterClass();
    }
}

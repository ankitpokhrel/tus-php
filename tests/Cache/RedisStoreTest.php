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

    /** @var Client|bool */
    protected static $connection;

    /** @var RedisStore|bool */
    protected static $redisStore;

    /**
     * Check redis connection.
     *
     * @return void
     */
    public static function setUpBeforeClass()
    {
        $connection = new RedisStore([
            'host' => getenv('REDIS_HOST'),
            'port' => getenv('REDIS_PORT'),
            'timeout' => getenv('REDIS_TIMEOUT'),
            'database' => getenv('REDIS_DATABASE'),
        ]);

        try {
            $connection->getRedis()->ping();

            static::$redisStore = $connection;
        } catch (ConnectionException $e) {
            static::$redisStore = false;
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
        if (false === static::$redisStore) {
            $this->markTestSkipped('Unable to connect to redis.');
        }

        $this->checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

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

        static::$redisStore->setTtl(1);

        $this->assertTrue(static::$redisStore->set($this->checksum, $cacheContent));
        $this->assertEquals($cacheContent, static::$redisStore->get($this->checksum));

        $string = 'Sherlock Holmes';

        array_push($cacheContent, $string);

        $this->assertTrue(static::$redisStore->set($this->checksum, $string));
        $this->assertEquals($cacheContent, static::$redisStore->get($this->checksum));
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
        $this->assertTrue(static::$redisStore->set($this->checksum, ['offset' => 500]));

        $contents = static::$redisStore->get($this->checksum);

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

        $this->assertNull(static::$redisStore->get($this->checksum));
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

        $this->assertTrue(static::$redisStore->set($this->checksum, $cacheContent));
        $this->assertEquals($cacheContent, static::$redisStore->get($this->checksum));
        $this->assertFalse(static::$redisStore->delete('invalid-checksum'));
        $this->assertTrue(static::$redisStore->delete($this->checksum));
        $this->assertNull(static::$redisStore->get($this->checksum));
    }

    /**
     * @test
     *
     * @covers ::getRedis
     */
    public function it_gets_redis_object()
    {
        $this->assertInstanceOf(Client::class, static::$redisStore->getRedis());
    }

    /**
     * Flush redis cache.
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        if (false !== static::$redisStore) {
            $redis = static::$redisStore->getRedis();

            $redis->flushall();
            $redis->disconnect();
        }

        parent::tearDownAfterClass();
    }
}

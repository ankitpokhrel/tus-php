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
     * @covers ::setPrefix
     * @covers ::getPrefix
     */
    public function it_sets_and_gets_redis_cache_prefix()
    {
        $this->assertEquals('tus:', static::$redisStore->getPrefix());
        $this->assertInstanceOf(RedisStore::class, static::$redisStore->setPrefix('redis:'));
        $this->assertEquals('redis:', static::$redisStore->getPrefix());
    }

    /**
     * @test
     *
     * @covers ::set
     * @covers ::get
     * @covers ::setTtl
     * @covers ::delete
     */
    public function it_sets_and_gets_cache_contents()
    {
        $cacheContent = ['expires_at' => 'Sat, 09 Dec 2017 16:25:51 GMT', 'offset' => 100];

        static::$redisStore->setTtl(1);

        $this->assertTrue(static::$redisStore->set($this->checksum, $cacheContent));
        $this->assertEquals($cacheContent, static::$redisStore->get($this->checksum));

        $string   = 'Sherlock Holmes';
        $checksum = '74f02d6da32082463e382f22';

        $this->assertTrue(static::$redisStore->set($checksum, $cacheContent));

        array_push($cacheContent, $string);

        $this->assertTrue(static::$redisStore->set($checksum, $string));
        $this->assertEquals($cacheContent, static::$redisStore->get($checksum));
        $this->assertTrue(static::$redisStore->delete($checksum));
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
     */
    public function it_returns_contents_if_not_expired()
    {
        $cacheContent = ['expires_at' => 'Sat, 09 Dec 2017 16:25:51 GMT', 'offset' => 500];

        $this->assertEquals($cacheContent, static::$redisStore->get($this->checksum));
    }

    /**
     * @test
     *
     * @covers ::get
     */
    public function it_returns_null_if_cache_is_expired()
    {
        $cacheContent = ['expires_at' => 'Thu, 07 Dec 2017 16:25:51 GMT', 'offset' => 100];

        $this->assertTrue(static::$redisStore->set($this->checksum, $cacheContent));
        $this->assertNull(static::$redisStore->get($this->checksum));
    }

    /**
     * @test
     *
     * @covers ::get
     */
    public function it_returns_expired_contents_if_with_expired_is_true()
    {
        $cacheContent = ['expires_at' => 'Thu, 07 Dec 2017 16:25:51 GMT', 'offset' => 100];

        $this->assertTrue(static::$redisStore->set($this->checksum, $cacheContent));
        $this->assertNull(static::$redisStore->get($this->checksum));
        $this->assertEquals($cacheContent, static::$redisStore->get($this->checksum, true));
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
     * @covers ::set
     * @covers ::get
     * @covers ::deleteAll
     */
    public function it_deletes_all_cache_keys()
    {
        $checksum1    = 'checksum-1';
        $checksum2    = 'checksum-2';
        $cacheContent = ['expires_at' => 'Fri, 08 Dec 2017 16:25:51 GMT', 'offset' => 100];

        $this->assertTrue(static::$redisStore->set($checksum1, $cacheContent));
        $this->assertTrue(static::$redisStore->set($checksum2, $cacheContent));
        $this->assertTrue(static::$redisStore->deleteAll([$checksum1, $checksum2]));
        $this->assertFalse(static::$redisStore->deleteAll([]));
        $this->assertNull(static::$redisStore->get($checksum1));
        $this->assertNull(static::$redisStore->get($checksum2));
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
     * @test
     *
     * @covers ::keys
     */
    public function it_gets_cache_keys()
    {
        $this->assertTrue(static::$redisStore->set($this->checksum, []));
        $this->assertEquals([static::$redisStore->getPrefix() . $this->checksum], static::$redisStore->keys());
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

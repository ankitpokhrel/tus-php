<?php

namespace TusPhp\Test\Cache;

use TusPhp\Cache\ApcuStore;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \TusPhp\Cache\ApcuStore
 */
class ApcuStoreTest extends TestCase
{
    /** @var boolean */
    protected static $extensionLoaded;

    /** @var ApcuStore */
    protected static $store;

    /** @var string */
    protected $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

    /**
     * Check apcu connection.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        self::$extensionLoaded = \extension_loaded('apcu');

        self::$store = new ApcuStore();

        parent::setUpBeforeClass();
    }

    /**
     * Prepare vars.
     *
     * @return void
     */
    protected function setUp(): void
    {
        if ( ! self::$extensionLoaded) {
            $this->markTestSkipped('APCU extension not loaded.');
        }

        parent::setUp();
    }

    /**
     * @test
     *
     * @covers ::setPrefix
     * @covers ::getPrefix
     */
    public function it_sets_and_gets_apcu_cache_prefix(): void
    {
        $this->assertEquals('tus:', static::$store->getPrefix());
        $this->assertInstanceOf(ApcuStore::class, static::$store->setPrefix('apcu:'));
        $this->assertEquals('apcu:', static::$store->getPrefix());
    }

    /**
     * @test
     *
     * @covers ::set
     * @covers ::get
     * @covers ::getActualCacheKey
     * @covers ::setTtl
     * @covers ::delete
     */
    public function it_sets_and_gets_cache_contents(): void
    {
        $cacheContent = ['expires_at' => 'Sat, 09 Dec 2017 16:25:51 GMT', 'offset' => 100];

        static::$store->setTtl(1);

        $this->assertTrue(static::$store->set($this->checksum, $cacheContent));
        $this->assertEquals($cacheContent, static::$store->get($this->checksum));

        $string   = 'Sherlock Holmes';
        $checksum = '74f02d6da32082463e382f22';

        $this->assertTrue(static::$store->set($checksum, $cacheContent));

        $cacheContent[] = $string;

        $this->assertTrue(static::$store->set($checksum, $string));
        $this->assertEquals($cacheContent, static::$store->get($checksum));
        $this->assertTrue(static::$store->delete($checksum));
    }

    /**
     * @test
     *
     * @covers ::set
     * @covers ::get
     * @covers ::getActualCacheKey
     *
     * @depends it_sets_and_gets_cache_contents
     */
    public function it_doesnt_replace_cache_key_in_set(): void
    {
        $this->assertTrue(static::$store->set($this->checksum, ['offset' => 500]));

        $contents = static::$store->get($this->checksum);

        $this->assertEquals(500, $contents['offset']);
        $this->assertEquals('Sat, 09 Dec 2017 16:25:51 GMT', $contents['expires_at']);
    }

    /**
     * @test
     *
     * @covers ::get
     * @covers ::getActualCacheKey
     */
    public function it_returns_contents_if_not_expired(): void
    {
        $cacheContent = ['expires_at' => 'Sat, 09 Dec 2017 16:25:51 GMT', 'offset' => 500];

        $this->assertEquals($cacheContent, static::$store->get($this->checksum));
    }

    /**
     * @test
     *
     * @covers ::get
     * @covers ::getActualCacheKey
     */
    public function it_returns_null_if_cache_is_expired(): void
    {
        $cacheContent = ['expires_at' => 'Thu, 07 Dec 2017 16:25:51 GMT', 'offset' => 100];

        $this->assertTrue(static::$store->set($this->checksum, $cacheContent));
        $this->assertNull(static::$store->get($this->checksum));
    }

    /**
     * @test
     *
     * @covers ::get
     * @covers ::getActualCacheKey
     */
    public function it_returns_expired_contents_if_with_expired_is_true(): void
    {
        $cacheContent = ['expires_at' => 'Thu, 07 Dec 2017 16:25:51 GMT', 'offset' => 100];

        $this->assertTrue(static::$store->set($this->checksum, $cacheContent));
        $this->assertNull(static::$store->get($this->checksum));
        $this->assertEquals($cacheContent, static::$store->get($this->checksum, true));
    }

    /**
     * @test
     *
     * @covers ::set
     * @covers ::get
     * @covers ::getActualCacheKey
     * @covers ::delete
     */
    public function it_deletes_cache_content(): void
    {
        $cacheContent = ['expires_at' => 'Fri, 08 Dec 2017 16:25:51 GMT', 'offset' => 100];

        $this->assertTrue(static::$store->set($this->checksum, $cacheContent));
        $this->assertEquals($cacheContent, static::$store->get($this->checksum));
        $this->assertFalse(static::$store->delete('invalid-checksum'));
        $this->assertTrue(static::$store->delete($this->checksum));
        $this->assertNull(static::$store->get($this->checksum));
    }

    /**
     * @test
     *
     * @covers ::set
     * @covers ::get
     * @covers ::getActualCacheKey
     * @covers ::deleteAll
     */
    public function it_deletes_all_cache_keys(): void
    {
        $checksum1    = 'checksum-1';
        $checksum2    = 'checksum-2';
        $cacheContent = ['expires_at' => 'Fri, 08 Dec 2017 16:25:51 GMT', 'offset' => 100];

        $this->assertTrue(static::$store->set($checksum1, $cacheContent));
        $this->assertTrue(static::$store->set($checksum2, $cacheContent));
        $this->assertTrue(static::$store->deleteAll([$checksum1, $checksum2]));
        $this->assertFalse(static::$store->deleteAll([]));
        $this->assertNull(static::$store->get($checksum1));
        $this->assertNull(static::$store->get($checksum2));
    }

    /**
     * @test
     *
     * @covers ::keys
     */
    public function it_gets_cache_keys(): void
    {
        $this->assertTrue(static::$store->set($this->checksum, []));
        $this->assertEquals([static::$store->getPrefix() . $this->checksum], static::$store->keys());
    }
}

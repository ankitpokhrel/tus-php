<?php

namespace TusPhp\Test\Cache;

use TusPhp\Cache\FileStore;
use TusPhp\Cache\RedisStore;
use TusPhp\Cache\ApcuStore;
use TusPhp\Cache\CacheFactory;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \TusPhp\Cache\CacheFactory
 */
class CacheFactoryTest extends TestCase
{
    /**
     * @test
     *
     * @covers ::make
     */
    public function it_makes_cache_store_object() : void
    {
        self::assertInstanceOf(FileStore::class, CacheFactory::make());
        self::assertInstanceOf(RedisStore::class, CacheFactory::make('redis'));
        self::assertInstanceOf(ApcuStore::class, CacheFactory::make('apcu'));
        self::assertInstanceOf(FileStore::class, CacheFactory::make('invalid'));
    }
}

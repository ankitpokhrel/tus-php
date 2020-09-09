<?php

namespace TusPhp\Test\Tus;

use TusPhp\Cache\FileStore;
use TusPhp\Cache\RedisStore;
use PHPUnit\Framework\TestCase;
use TusPhp\Tus\Server as TusServer;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @coversDefaultClass \TusPhp\Tus\AbstractTus
 */
class AbstractTusTest extends TestCase
{
    /** @var TusServer */
    protected $tus;

    /**
     * Prepare vars.
     *
     * @return void
     */
    public function setUp() : void
    {
        $this->tus = new TusServer;

        parent::setUp();
    }

    /**
     * @test
     *
     * @covers ::getCache
     * @covers ::setCache
     */
    public function it_sets_and_gets_cache() : void
    {
        self::assertInstanceOf(FileStore::class, $this->tus->getCache());

        $this->tus->setCache('redis');

        self::assertInstanceOf(RedisStore::class, $this->tus->getCache());

        $fileStore = new FileStore;

        self::assertInstanceOf(TusServer::class, $this->tus->setCache($fileStore));
        self::assertInstanceOf(FileStore::class, $this->tus->getCache());
    }

    /**
     * @test
     *
     * @covers ::getApiPath
     * @covers ::setApiPath
     */
    public function it_sets_and_gets_api_path() : void
    {
        self::assertEquals('/files', $this->tus->getApiPath());
        self::assertInstanceOf(TusServer::class, $this->tus->setApiPath('/api'));
        self::assertEquals('/api', $this->tus->getApiPath());
    }

    /**
     * @test
     *
     * @covers ::event
     * @covers ::setDispatcher
     */
    public function it_sets_and_gets_event_dispatcher() : void
    {
        self::assertInstanceOf(EventDispatcher::class, $this->tus->event());

        $eventDispatcher = new EventDispatcher();

        self::assertInstanceOf(TusServer::class, $this->tus->setDispatcher($eventDispatcher));
        self::assertEquals($eventDispatcher, $this->tus->event());
    }
}

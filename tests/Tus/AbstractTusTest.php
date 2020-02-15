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
    public function setUp()
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
    public function it_sets_and_gets_cache()
    {
        $this->assertInstanceOf(FileStore::class, $this->tus->getCache());

        $this->tus->setCache('redis');

        $this->assertInstanceOf(RedisStore::class, $this->tus->getCache());

        $fileStore = new FileStore;

        $this->assertInstanceOf(TusServer::class, $this->tus->setCache($fileStore));
        $this->assertInstanceOf(FileStore::class, $this->tus->getCache());
    }

    /**
     * @test
     *
     * @covers ::getApiPath
     * @covers ::setApiPath
     */
    public function it_sets_and_gets_api_path()
    {
        $this->assertEquals('/files', $this->tus->getApiPath());
        $this->assertInstanceOf(TusServer::class, $this->tus->setApiPath('/api'));
        $this->assertEquals('/api', $this->tus->getApiPath());
    }

    /**
     * @test
     *
     * @covers ::event
     * @covers ::setDispatcher
     */
    public function it_sets_and_gets_event_dispatcher()
    {
        $this->assertInstanceOf(EventDispatcher::class, $this->tus->event());

        $eventDispatcher = new EventDispatcher();

        $this->assertInstanceOf(TusServer::class, $this->tus->setDispatcher($eventDispatcher));
        $this->assertEquals($eventDispatcher, $this->tus->event());
    }
}

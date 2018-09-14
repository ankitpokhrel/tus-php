<?php

namespace TusPhp\Test\Tus;

use TusPhp\Cache\FileStore;
use PHPUnit\Framework\TestCase;
use TusPhp\Tus\Client as TusClient;

/**
 * @coversDefaultClass \TusPhp\Tus\AbstractTus
 */
class AbstractTusTest extends TestCase
{
    /** @var TusClient */
    protected $tus;

    /**
     * Prepare vars.
     *
     * @return void
     */
    public function setUp()
    {
        $this->tus = new TusClient('http://www.example.com/');

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

        $fileStore = new FileStore;

        $this->assertInstanceOf(TusClient::class, $this->tus->setCache($fileStore));
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
        $this->assertInstanceOf(TusClient::class, $this->tus->setApiPath('/api'));
        $this->assertEquals('/api', $this->tus->getApiPath());
    }
}

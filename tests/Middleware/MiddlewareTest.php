<?php

namespace TusPhp\Test\Middleware;

use Mockery as m;
use TusPhp\Middleware\Cors;
use PHPUnit\Framework\TestCase;
use TusPhp\Middleware\Middleware;
use TusPhp\Middleware\GlobalHeaders;
use TusPhp\Test\Fixtures\TestMiddleware;

/**
 * @coversDefaultClass \TusPhp\Middleware\Middleware
 */
class MiddlewareTest extends TestCase
{
    /** @var Middleware */
    protected $middleware;

    /**
     * Prepare vars.
     *
     * @return void
     */
    public function setUp()
    {
        $this->middleware = new Middleware;

        parent::setUp();
    }

    /**
     * @test
     *
     * @covers ::__construct
     * @covers ::list
     */
    public function it_sets_default_middleware()
    {
        $middleware = $this->middleware->list();

        $this->assertInstanceOf(GlobalHeaders::class, $middleware[GlobalHeaders::class]);
        $this->assertInstanceOf(Cors::class, $middleware[Cors::class]);
    }

    /**
     * @test
     *
     * @covers ::add
     * @covers ::list
     */
    public function it_adds_valid_middleware()
    {
        $corsMock  = m::mock(Cors::class);
        $mockClass = get_class($corsMock);

        $this->assertInstanceOf(Middleware::class, $this->middleware->add($corsMock, TestMiddleware::class));

        $middleware = $this->middleware->list();

        $this->assertCount(4, $middleware);
        $this->assertInstanceOf($mockClass, $middleware[$mockClass]);
        $this->assertInstanceOf(Cors::class, $middleware[Cors::class]);
        $this->assertInstanceOf(GlobalHeaders::class, $middleware[GlobalHeaders::class]);
        $this->assertInstanceOf(TestMiddleware::class, $middleware[TestMiddleware::class]);
    }

    /**
     * @test
     *
     * @covers ::list
     */
    public function it_gets_middleware_list()
    {
        $this->assertCount(2, $this->middleware->list());
    }

    /**
     * @test
     *
     * @covers ::skip
     * @covers ::list
     */
    public function it_skips_given_middleware()
    {
        $this->assertInstanceOf(Middleware::class, $this->middleware->skip(GlobalHeaders::class));

        $middleware = $this->middleware->list();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(Cors::class, $middleware[Cors::class]);
    }

    /**
     * Close mockery connection.
     *
     * @return void.
     */
    public function tearDown()
    {
        m::close();

        parent::tearDown();
    }
}

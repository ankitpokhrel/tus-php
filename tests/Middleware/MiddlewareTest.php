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
    public function setUp() : void
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
    public function it_sets_default_middleware() : void
    {
        $middleware = $this->middleware->list();

        self::assertInstanceOf(GlobalHeaders::class, $middleware[GlobalHeaders::class]);
        self::assertInstanceOf(Cors::class, $middleware[Cors::class]);
    }

    /**
     * @test
     *
     * @covers ::add
     * @covers ::list
     */
    public function it_adds_valid_middleware() : void
    {
        $corsMock  = m::mock(Cors::class);
        $mockClass = \get_class($corsMock);

        self::assertInstanceOf(Middleware::class, $this->middleware->add($corsMock, TestMiddleware::class));

        $middleware = $this->middleware->list();

        self::assertCount(4, $middleware);
        self::assertInstanceOf($mockClass, $middleware[$mockClass]);
        self::assertInstanceOf(Cors::class, $middleware[Cors::class]);
        self::assertInstanceOf(GlobalHeaders::class, $middleware[GlobalHeaders::class]);
        self::assertInstanceOf(TestMiddleware::class, $middleware[TestMiddleware::class]);
    }

    /**
     * @test
     *
     * @covers ::list
     */
    public function it_gets_middleware_list() : void
    {
        self::assertCount(2, $this->middleware->list());
    }

    /**
     * @test
     *
     * @covers ::skip
     * @covers ::list
     */
    public function it_skips_given_middleware() : void
    {
        self::assertInstanceOf(Middleware::class, $this->middleware->skip(GlobalHeaders::class));

        $middleware = $this->middleware->list();

        self::assertCount(1, $middleware);
        self::assertInstanceOf(Cors::class, $middleware[Cors::class]);
    }

    /**
     * Close mockery connection.
     *
     * @return void.
     */
    public function tearDown() : void
    {
        m::close();

        parent::tearDown();
    }
}

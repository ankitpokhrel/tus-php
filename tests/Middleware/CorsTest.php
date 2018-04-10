<?php

namespace TusPhp\Test\Middleware;

use Mockery as m;
use TusPhp\Request;
use TusPhp\Response;
use TusPhp\Middleware\Cors;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \TusPhp\Middleware\Cors
 */
class CorsTest extends TestCase
{
    /** @var Cors */
    protected $cors;

    /**
     * Prepare vars.
     *
     * @return void
     */
    public function setUp()
    {
        $this->cors = new Cors;

        parent::setUp();
    }

    /**
     * @test
     *
     * @covers ::handle
     */
    public function it_adds_cors_headers()
    {
        $requestMock  = m::mock(Request::class, [])->makePartial();
        $responseMock = m::mock(Response::class);

        $responseMock
            ->shouldReceive('setHeaders')
            ->once()
            ->with([
                'Access-Control-Allow-Origin' => $requestMock->header('Origin'),
                'Access-Control-Allow-Methods' => implode(',', $requestMock->allowedHttpVerbs()),
                'Access-Control-Allow-Headers' => 'Origin, X-Requested-With, Content-Type, Content-Length, Upload-Key, Upload-Checksum, Upload-Length, Upload-Offset, Tus-Version, Tus-Resumable, Upload-Metadata',
                'Access-Control-Expose-Headers' => 'Upload-Key, Upload-Checksum, Upload-Length, Upload-Offset, Upload-Metadata, Tus-Version, Tus-Resumable, Tus-Extension, Location',
                'Access-Control-Max-Age' => 86400,
            ])
            ->andReturnSelf();

        $this->assertNull($this->cors->handle($requestMock, $responseMock));
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

<?php

namespace TusPhp\Test;

use TusPhp\Response;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \TusPhp\Response
 */
class ResponseTest extends TestCase
{
    /** @var Response */
    protected $response;

    /**
     * Prepare vars.
     *
     * @return void
     */
    public function setUp()
    {
        $this->response = new Response;
    }

    /**
     * @test
     *
     * @covers ::__construct
     * @covers ::createOnly
     * @covers ::getCreateOnly
     */
    public function it_sets_and_gets_create_only()
    {
        $this->assertFalse($this->response->getCreateOnly());

        $this->response->createOnly(true);

        $this->assertTrue($this->response->getCreateOnly());
    }

    /**
     * @test
     *
     * @covers ::send
     */
    public function it_sends_a_response()
    {
        $content  = '204 No Content';
        $response = $this->response->createOnly(true)->send($content, 204, ['Offset' => 100]);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals($content, $response->getContent());
        $this->assertEquals(100, $response->headers->get('Offset'));
    }

    /**
     * @test
     *
     * @covers ::download
     *
     * @expectedException \Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException
     */
    public function it_returns_file_not_found_exception()
    {
        $file = __DIR__ . '/Fixtures/404.txt';

        $this->response->createOnly(true)->download($file, null);
    }

    /**
     * @test
     *
     * @covers ::download
     */
    public function it_sends_binary_response()
    {
        $file = __DIR__ . '/Fixtures/empty.txt';
        $name = 'file.txt';

        $response = $this->response->createOnly(true)->download($file, $name);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("attachment; filename=\"$name\"", $response->headers->get('content-disposition'));
    }

    /**
     * @test
     *
     * @covers ::download
     */
    public function it_sends_binary_response_when_name_is_null()
    {
        $file = __DIR__ . '/Fixtures/empty.txt';

        $response = $this->response->createOnly(true)->download($file, null, [], 'inline');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("inline; filename=\"empty.txt\"", $response->headers->get('content-disposition'));
    }
}

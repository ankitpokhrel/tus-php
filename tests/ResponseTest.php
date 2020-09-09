<?php

namespace TusPhp\Test;

use TusPhp\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

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
    public function setUp() : void
    {
        $this->response = new Response;

        parent::setUp();
    }

    /**
     * @test
     *
     * @covers ::createOnly
     * @covers ::getCreateOnly
     */
    public function it_sets_and_gets_create_only() : void
    {
        self::assertTrue($this->response->getCreateOnly());

        $this->response->createOnly(false);

        self::assertFalse($this->response->getCreateOnly());
    }

    /**
     * @test
     *
     * @covers ::setHeaders
     * @covers ::getHeaders
     */
    public function it_sets_and_gets_headers() : void
    {
        self::assertEquals([], $this->response->getHeaders());

        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Max-Age' => 86400,
        ];

        self::assertInstanceOf(Response::class, $this->response->setHeaders($headers));
        self::assertInstanceOf(
            Response::class,
            $this->response->setHeaders(['Access-Control-Allow-Methods' => 'GET,POST'])
        );
        self::assertEquals($headers + ['Access-Control-Allow-Methods' => 'GET,POST'], $this->response->getHeaders());
    }

    /**
     * @test
     *
     * @covers ::setHeaders
     * @covers ::replaceHeaders
     * @covers ::getHeaders
     */
    public function it_replaces_headers() : void
    {
        self::assertEquals([], $this->response->getHeaders());
        self::assertInstanceOf(Response::class, $this->response->setHeaders(['Access-Control-Max-Age' => 86400]));
        self::assertInstanceOf(
            Response::class,
            $this->response->replaceHeaders(['Access-Control-Allow-Methods' => 'GET,POST'])
        );
        self::assertEquals(['Access-Control-Allow-Methods' => 'GET,POST'], $this->response->getHeaders());
    }

    /**
     * @test
     *
     * @covers ::send
     */
    public function it_sends_a_response() : void
    {
        $content  = '204 No Content';
        $response = $this->response
            ->createOnly(true)
            ->setHeaders(['Access-Control-Max-Age' => 86400])
            ->send($content, 204, ['Offset' => 100]);

        self::assertEquals(204, $response->getStatusCode());
        self::assertEquals($content, $response->getContent());
        self::assertEquals(86400, $response->headers->get('Access-Control-Max-Age'));
        self::assertEquals(100, $response->headers->get('Offset'));
    }

    /**
     * @test
     *
     * @covers ::send
     */
    public function it_sends_array_response() : void
    {
        $content  = ['status' => '204 No Content'];
        $response = $this->response
            ->createOnly(true)
            ->send($content, 204, ['Offset' => 100]);

        self::assertEquals(204, $response->getStatusCode());
        self::assertEquals(json_encode($content), $response->getContent());
        self::assertEquals(100, $response->headers->get('Offset'));
    }

    /**
     * @test
     *
     * @covers ::download
     */
    public function it_returns_file_not_found_exception() : void
    {
        $this->expectException(FileNotFoundException::class);
        $file = __DIR__ . '/Fixtures/404.txt';

        $this->response->createOnly(true)->download($file, null);
    }

    /**
     * @test
     *
     * @covers ::download
     */
    public function it_sends_binary_response() : void
    {
        $file = __DIR__ . '/Fixtures/empty.txt';
        $name = 'file.txt';

        $response = $this->response->createOnly(true)->download($file, $name);

        self::assertEquals(200, $response->getStatusCode());
        self::assertRegExp("/attachment; filename=($name|\"$name\")/", $response->headers->get('content-disposition'));
    }

    /**
     * @test
     *
     * @covers ::download
     */
    public function it_sends_binary_response_when_name_is_null() : void
    {
        $file = __DIR__ . '/Fixtures/empty.txt';

        $response = $this->response->createOnly(true)->download($file, null, [], 'inline');

        self::assertEquals(200, $response->getStatusCode());
        self::assertRegExp(
            '/inline; filename=(empty.txt|"empty.txt")/',
            $response->headers->get('content-disposition')
        );
    }
}

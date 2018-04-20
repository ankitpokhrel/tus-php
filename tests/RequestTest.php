<?php

namespace TusPhp\Test;

use TusPhp\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

/**
 * @coversDefaultClass \TusPhp\Request
 */
class RequestTest extends TestCase
{
    /** @var Request */
    protected $request;

    /**
     * Prepare vars.
     *
     * @return void
     */
    public function setUp()
    {
        $this->request = new Request;

        parent::setUp();
    }

    /**
     * @test
     *
     * @covers ::__construct
     * @covers ::method
     */
    public function it_returns_current_request_method()
    {
        $this->assertEquals('GET', $this->request->method());

        $request = new Request;

        $request->getRequest()->server->set('REQUEST_METHOD', 'POST');

        $this->assertEquals('POST', $request->method());
    }

    /**
     * @test
     *
     * @covers ::key
     */
    public function it_should_return_checksum_from_request_url()
    {
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->request->getRequest()->server->set('REQUEST_URI', '/files/' . $checksum);

        $this->assertEquals($checksum, $this->request->key());
    }

    /**
     * @test
     *
     * @covers ::path
     */
    public function it_returns_url_path_info()
    {
        $this->request->getRequest()->server->set('REQUEST_URI', '/tus/files/');

        $this->assertEquals('/tus/files/', $this->request->path());
    }

    /**
     * @test
     *
     * @covers ::allowedHttpVerbs
     */
    public function it_returns_allowed_http_verbs()
    {
        $this->assertEquals([
            'GET',
            'POST',
            'PATCH',
            'DELETE',
            'HEAD',
            'OPTIONS',
        ], $this->request->allowedHttpVerbs());
    }

    /**
     * @test
     *
     * @covers ::header
     */
    public function it_extracts_header()
    {
        $this->request->getRequest()->headers->set('content-length', 100);

        $this->assertEquals(100, $this->request->header('content-length'));
    }

    /**
     * @test
     *
     * @covers ::url
     */
    public function it_gets_root_url()
    {
        $this->request->getRequest()->server->add([
            'SERVER_NAME' => 'tus.local',
            'SERVER_PORT' => 80,
        ]);

        $this->assertEquals('http://tus.local', $this->request->url());
    }

    /**
     * @test
     *
     * @covers ::extractFileName
     */
    public function it_return_null_if_it_cannot_extract_filename()
    {
        $this->assertNull($this->request->extractFileName());
    }

    /**
     * @test
     *
     * @covers ::extractFromHeader
     */
    public function it_extracts_data_from_header()
    {
        $this->request->getRequest()->headers->set('Upload-Metadata', 'filename test');
        $this->request->getRequest()->headers->set('Upload-Concat', 'final;/files/a /files/b');

        $this->assertEquals([], $this->request->extractFromHeader('Upload-Metadata', 'invalid'));
        $this->assertEquals(['test'], $this->request->extractFromHeader('Upload-Metadata', 'filename'));
        $this->assertEquals(['/files/a', '/files/b'], $this->request->extractFromHeader('Upload-Concat', 'final;'));
    }

    /**
     * @test
     *
     * @covers ::extractFromHeader
     * @covers ::extractFileName
     */
    public function it_extracts_file_name()
    {
        $filename = 'file.txt';

        $this->request->getRequest()->headers->set('Upload-Metadata', 'filename ' . base64_encode($filename));

        $this->assertEquals($filename, $this->request->extractFileName());
    }

    /**
     * @test
     *
     * @covers ::extractFileName
     */
    public function it_extracts_file_name_from_concatenated_headers()
    {
        $filename = 'file.txt';

        $this->request
            ->getRequest()
            ->headers
            ->set('Upload-Metadata', 'filename ' . base64_encode($filename) . ',type image');

        $this->assertEquals($filename, $this->request->extractFileName());
    }

    /**
     * @test
     *
     * @covers ::extractFileName
     */
    public function it_extracts_file_name_from_multiple_concatenated_headers()
    {
        $filename = 'file.txt';

        $this->request
            ->getRequest()
            ->headers
            ->set('Upload-Metadata', 'name ' . base64_encode($filename) . ',type image,accept jpeg');

        $this->assertEquals($filename, $this->request->extractFileName());
    }

    /**
     * @test
     *
     * @covers ::extractFromHeader
     * @covers ::extractPartials
     */
    public function it_extracts_partials()
    {
        $this->request->getRequest()->headers->set('Upload-Concat', 'final;/files/a /files/b');

        $this->assertEquals(['/files/a', '/files/b'], $this->request->extractPartials());
    }

    /**
     * @test
     *
     * @covers ::isPartial
     */
    public function it_checks_if_a_request_is_partial()
    {
        $this->assertFalse($this->request->isPartial());

        $this->request->getRequest()->headers->set('Upload-Concat', 'partial');

        $this->assertTrue($this->request->isPartial());
    }

    /**
     * @test
     *
     * @covers ::isFinal
     */
    public function it_checks_if_a_request_is_final()
    {
        $this->assertFalse($this->request->isFinal());

        $this->request->getRequest()->headers->set('Upload-Concat', 'final;/files/a /files/b');

        $this->assertTrue($this->request->isFinal());
    }

    /**
     * @test
     *
     * @covers ::getRequest
     */
    public function it_gets_request()
    {
        $this->assertInstanceOf(HttpRequest::class, $this->request->getRequest());
    }
}

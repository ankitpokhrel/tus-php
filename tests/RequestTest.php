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
    public function setUp() : void
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
    public function it_returns_current_request_method() : void
    {
        self::assertEquals('GET', $this->request->method());

        $request = new Request;

        $request->getRequest()->server->set('REQUEST_METHOD', 'POST');

        self::assertEquals('POST', $request->method());
    }

    /**
     * @test
     *
     * @covers ::key
     */
    public function it_should_return_checksum_from_request_url() : void
    {
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->request->getRequest()->server->set('REQUEST_URI', '/files/' . $checksum);

        self::assertEquals($checksum, $this->request->key());
    }

    /**
     * @test
     *
     * @covers ::path
     */
    public function it_returns_url_path_info() : void
    {
        $this->request->getRequest()->server->set('REQUEST_URI', '/tus/files/');

        self::assertEquals('/tus/files/', $this->request->path());
    }

    /**
     * @test
     *
     * @covers ::allowedHttpVerbs
     */
    public function it_returns_allowed_http_verbs() : void
    {
        self::assertEquals([
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
    public function it_extracts_header() : void
    {
        $this->request->getRequest()->headers->set('content-length', 100);

        self::assertEquals(100, $this->request->header('content-length'));
    }

    /**
     * @test
     *
     * @covers ::url
     */
    public function it_gets_root_url() : void
    {
        $this->request->getRequest()->server->add([
            'SERVER_NAME' => 'tus.local',
            'SERVER_PORT' => 80,
        ]);

        self::assertEquals('http://tus.local', $this->request->url());

        $this->request->getRequest()->server->add([
            'REQUEST_URI' => '/tus/files/',
            'QUERY_STRING' => 'token=random&path=root',
        ]);

        self::assertEquals('http://tus.local', $this->request->url());
    }

    /**
     * @test
     *
     * @covers ::extractFromHeader
     */
    public function it_extracts_data_from_header() : void
    {
        $this->request->getRequest()->headers->set('Upload-Metadata', 'filename dGVzdA==');
        $this->request->getRequest()->headers->set('Upload-Concat', 'final;/files/a /files/b');

        self::assertEquals([], $this->request->extractFromHeader('Upload-Metadata', 'invalid'));
        self::assertEquals(['dGVzdA=='], $this->request->extractFromHeader('Upload-Metadata', 'filename'));
        self::assertEquals(['/files/a', '/files/b'], $this->request->extractFromHeader('Upload-Concat', 'final;'));
    }

    /**
     * @test
     *
     * @covers ::extractMeta
     * @covers ::extractFileName
     * @covers ::isValidFilename
     */
    public function it_extracts_file_name() : void
    {
        $validFileNames = [
            'file.txt',
            'file-1.png',
            'some-long-file-name.txt',
            'file_with_underscore-and-dash.jpg',
            'file@with-special-chars-€.pdf',
            'äöü.txt',
            'ファイル.pdf',
            '文件-파일.mp4',
            'ชื่อไฟล์.zip',
            'फाइल.pdf',
            ' spaces are valid filenames.woah'
        ];

        foreach ($validFileNames as $filename) {
            $this->request->getRequest()->headers->set('Upload-Metadata', 'name ' . base64_encode($filename));
            self::assertEquals($filename, $this->request->extractFileName());
        }
    }

    /**
     * @test
     *
     * @covers ::extractMeta
     * @covers ::extractFileName
     * @covers ::isValidFilename
     */
    public function it_replaces_bad_file_names() : void
    {
        $badFileNames = [
            '../file.txt',
            '../',
            'file/../a.png',
            'a:b.png',
            ':',
            '../../path/to/file.txt',
            '~/file.txt',
            '../some/path/../file.txt',
        ];

        foreach ($badFileNames as $filename) {
            $this->request->getRequest()->headers->set('Upload-Metadata', 'name ' . base64_encode($filename));
            self::assertEquals('', $this->request->extractFileName());
        }
    }

    /**
     * @test
     *
     * @covers ::extractMeta
     * @covers ::extractFileName
     * @covers ::extractAllMeta
     */
    public function it_extracts_metadata_from_multiple_concatenated_headers() : void
    {
        $filename = 'file.txt';
        $fileType = 'image';
        $accept   = 'image/jpeg';

        $this->request
            ->getRequest()
            ->headers
            ->set(
                'Upload-Metadata',
                sprintf(
                    'filename %s,type %s,accept %s',
                    base64_encode($filename),
                    base64_encode($fileType),
                    base64_encode($accept)
                )
            );

        self::assertEquals($filename, $this->request->extractFileName());
        self::assertEquals($fileType, $this->request->extractMeta('type'));
        self::assertEquals($accept, $this->request->extractMeta('accept'));
        self::assertEquals(['filename' => $filename, 'type' => $fileType, 'accept' => $accept], $this->request->extractAllMeta());
    }

    /**
     * @test
     *
     * @covers ::extractMeta
     * @covers ::extractAllMeta
     */
    public function it_returns_empty_if_upload_metadata_header_not_present() : void
    {
        self::assertEmpty($this->request->extractMeta('invalid-key'));

        $this->request
            ->getRequest()
            ->headers
            ->set('Upload-Metadata', '');

        self::assertEmpty($this->request->extractMeta('invalid-key'));
        self::assertEmpty($this->request->extractAllMeta());
    }

    /**
     * @test
     *
     * @covers ::extractFromHeader
     * @covers ::extractPartials
     */
    public function it_extracts_partials() : void
    {
        $this->request->getRequest()->headers->set('Upload-Concat', 'final;/files/a /files/b');

        self::assertEquals(['/files/a', '/files/b'], $this->request->extractPartials());
    }

    /**
     * @test
     *
     * @covers ::isPartial
     */
    public function it_checks_if_a_request_is_partial() : void
    {
        self::assertFalse($this->request->isPartial());

        $this->request->getRequest()->headers->set('Upload-Concat', 'partial');

        self::assertTrue($this->request->isPartial());
    }

    /**
     * @test
     *
     * @covers ::isFinal
     */
    public function it_checks_if_a_request_is_final() : void
    {
        self::assertFalse($this->request->isFinal());

        $this->request->getRequest()->headers->set('Upload-Concat', 'final;/files/a /files/b');

        self::assertTrue($this->request->isFinal());
    }

    /**
     * @test
     *
     * @covers ::getRequest
     */
    public function it_gets_request() : void
    {
        self::assertInstanceOf(HttpRequest::class, $this->request->getRequest());
    }

    /**
     * @test
     *
     * @covers ::extractAllMeta
     */
    public function it_extracts_all_metadata() : void
    {
        $this->request->getRequest()->headers->set('Upload-Metadata', '');
        self::assertEmpty($this->request->extractAllMeta());

        $uploadMetadata = array(
            'filename' => 'file.txt',
            'type' => 'image',
            'accept' => 'image/jpeg'
        );

        $this->request
            ->getRequest()
            ->headers
            ->set(
                'Upload-Metadata',
                sprintf(
                    'filename %s,type %s,accept %s',
                    base64_encode($uploadMetadata['filename']),
                    base64_encode($uploadMetadata['type']),
                    base64_encode($uploadMetadata['accept'])
                ),
                true
            );

        self::assertEquals($uploadMetadata, $this->request->extractAllMeta());
    }
}

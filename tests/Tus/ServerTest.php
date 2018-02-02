<?php

namespace TusPhp\Test;

use TusPhp\File;
use Mockery as m;
use TusPhp\Request;
use TusPhp\Response;
use phpmock\MockBuilder;
use TusPhp\Cache\FileStore;
use PHPUnit\Framework\TestCase;
use TusPhp\Tus\Server as TusServer;
use TusPhp\Exception\FileException;
use TusPhp\Exception\ConnectionException;
use TusPhp\Exception\OutOfRangeException;
use Illuminate\Http\Response as HttpResponse;

/**
 * @coversDefaultClass \TusPhp\Tus\Server
 */
class ServerTest extends TestCase
{
    /** @const array */
    const ALLOWED_HTTP_VERBS = ['GET', 'POST', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];

    /** @var TusServer */
    protected $tusServer;

    /** @var \Mockery mock */
    protected $tusServerMock;

    /**
     * Prepare vars.
     *
     * @return void
     */
    public function setUp()
    {
        $this->tusServer     = new TusServer;
        $this->tusServerMock = m::mock(TusServer::class)
                                ->shouldAllowMockingProtectedMethods()
                                ->makePartial();

        $this->tusServerMock
            ->shouldReceive('setCache')
            ->once()
            ->with('file')
            ->andReturnSelf();

        $this->tusServerMock->__construct('file');

        $this->tusServerMock
            ->shouldReceive('exit')
            ->andReturn(null);

        $this->tusServerMock
            ->shouldReceive('__call')
            ->andReturn(null);

        parent::setUp();
    }

    /**
     * @test
     *
     * @covers ::__construct
     * @covers ::getUploadDir
     * @covers ::setUploadDir
     */
    public function it_sets_and_gets_upload_dir()
    {
        $this->assertEquals(dirname(__DIR__, 2) . '/uploads', $this->tusServer->getUploadDir());

        $this->tusServer->setUploadDir(dirname(__DIR__) . '/storage');

        $this->assertEquals(dirname(__DIR__) . '/storage', $this->tusServer->getUploadDir());
    }

    /**
     * @test
     *
     * @covers ::__construct
     * @covers ::getRequest
     */
    public function it_gets_a_request()
    {
        $this->assertInstanceOf(Request::class, $this->tusServer->getRequest());
    }

    /**
     * @test
     *
     * @covers ::__construct
     * @covers ::getResponse
     */
    public function it_gets_a_response()
    {
        $this->assertInstanceOf(Response::class, $this->tusServer->getResponse());
    }

    /**
     * @test
     *
     * @covers ::getChecksum
     */
    public function it_gets_a_checksum()
    {
        $filePath = __DIR__ . '/../Fixtures/empty.txt';
        $checksum = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

        $this->assertEquals($checksum, $this->tusServer->getChecksum($filePath));
    }

    /**
     * @test
     *
     * @covers ::getChecksumAlgorithm
     */
    public function it_gets_checksum_algorithm()
    {
        $this->assertEquals('sha256', $this->tusServerMock->getChecksumAlgorithm());

        $checksumAlgorithm = 'sha1';

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->headers
            ->set('Upload-Checksum', $checksumAlgorithm . ' checksum');

        $this->assertEquals($checksumAlgorithm, $this->tusServerMock->getChecksumAlgorithm());
    }

    /**
     * @test
     *
     * @covers ::serve
     */
    public function it_sends_405_for_invalid_http_verbs()
    {
        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->set('REQUEST_METHOD', 'INVALID');

        $response = $this->tusServerMock->serve();

        $this->assertEquals(405, $response->getStatusCode());
        $this->assertNull($response->getOriginalContent());
    }

    /**
     * @test
     *
     * @covers ::serve
     */
    public function it_calls_proper_handle_method()
    {
        foreach (self::ALLOWED_HTTP_VERBS as $method) {
            $tusServerMock = m::mock(TusServer::class)
                              ->shouldAllowMockingProtectedMethods()
                              ->makePartial();

            $tusServerMock
                ->shouldReceive('setCache')
                ->once()
                ->with('file')
                ->andReturnSelf();

            $tusServerMock->__construct('file');

            $tusServerMock
                ->shouldReceive('exit')
                ->once()
                ->andReturn(null);

            $tusServerMock
                ->getRequest()
                ->getRequest()
                ->server
                ->set('REQUEST_METHOD', $method);

            $tusServerMock
                ->shouldReceive('handle' . ucfirst(strtolower($method)))
                ->once()
                ->andReturn(m::mock(HttpResponse::class));

            $this->assertNull($tusServerMock->serve());
        }
    }

    /**
     * @test
     *
     * @covers ::__call
     */
    public function it_sends_400_for_other_methods()
    {
        $response = $this->tusServer->handleHead();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertNull($response->getOriginalContent());
    }

    /**
     * @test
     *
     * @covers ::handleOptions
     */
    public function it_handles_options_request()
    {
        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->set('REQUEST_METHOD', 'OPTIONS');

        $response = $this->tusServerMock->handleOptions();
        $headers  = $response->headers->all();

        $this->assertEquals(self::ALLOWED_HTTP_VERBS, $headers['allow']);
        $this->assertEquals('1.0.0', current($headers['tus-version']));
        $this->assertEquals(
            'creation,termination,checksum,expiration,concatenation',
            current($headers['tus-extension'])
        );
    }

    /**
     * @test
     *
     * @covers ::handleHead
     */
    public function it_sends_404_for_invalid_checksum_in_head_method()
    {
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $server = $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server;

        $server->add([
            'REQUEST_METHOD' => 'HEAD',
            'REQUEST_URI' => '/files/' . $checksum,
        ]);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with($checksum)
            ->andReturn(null);

        $this->tusServerMock->setCache($cacheMock);

        $response = $this->tusServerMock->handleHead();

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertNull($response->getOriginalContent());
    }

    /**
     * @test
     *
     * @covers ::handleHead
     */
    public function it_returns_410_if_no_offset_is_set()
    {
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->add([
                'REQUEST_METHOD' => 'HEAD',
                'REQUEST_URI' => '/files/' . $checksum,
            ]);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->twice()
            ->with($checksum)
            ->andReturn([
                'name' => 'file.txt',
            ]);

        $this->tusServerMock->setCache($cacheMock);

        $response = $this->tusServerMock->handleHead();

        $this->assertEquals(410, $response->getStatusCode());
        $this->assertNull($response->getOriginalContent());
    }

    /**
     * @test
     *
     * @covers ::handleHead
     */
    public function it_handles_head_request()
    {
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->add([
                'REQUEST_METHOD' => 'HEAD',
                'REQUEST_URI' => '/files/' . $checksum,
            ]);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->twice()
            ->with($checksum)
            ->andReturn([
                'offset' => 49,
                'upload_type' => 'normal',
            ]);

        $this->tusServerMock->setCache($cacheMock);

        $response = $this->tusServerMock->handleHead();

        $this->assertNull($response->getOriginalContent());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(49, $response->headers->get('upload-offset'));
        $this->assertNull($response->headers->get('upload-concat'));
        $this->assertEquals('1.0.0', $response->headers->get('tus-resumable'));
        $this->assertEquals('no-store, private', $response->headers->get('cache-control'));
    }

    /**
     * @test
     *
     * @covers ::handleHead
     */
    public function it_handles_head_request_for_partial_upload()
    {
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->add([
                'REQUEST_METHOD' => 'HEAD',
                'REQUEST_URI' => '/files/' . $checksum,
            ]);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->twice()
            ->with($checksum)
            ->andReturn([
                'offset' => 49,
                'upload_type' => 'partial',
            ]);

        $this->tusServerMock->setCache($cacheMock);

        $response = $this->tusServerMock->handleHead();

        $this->assertNull($response->getOriginalContent());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(49, $response->headers->get('upload-offset'));
        $this->assertEquals('partial', $response->headers->get('upload-concat'));
        $this->assertEquals('1.0.0', $response->headers->get('tus-resumable'));
        $this->assertEquals('no-store, private', $response->headers->get('cache-control'));
    }

    /**
     * @test
     *
     * @covers ::handleHead
     */
    public function it_handles_head_request_for_final_upload()
    {
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->add([
                'REQUEST_METHOD' => 'HEAD',
                'REQUEST_URI' => '/files/' . $checksum,
            ]);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->twice()
            ->with($checksum)
            ->andReturn([
                'offset' => 49,
                'upload_type' => 'final',
            ]);

        $this->tusServerMock->setCache($cacheMock);

        $response = $this->tusServerMock->handleHead();

        $this->assertNull($response->getOriginalContent());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(49, $response->headers->get('upload-offset'));
        $this->assertEquals('final', $response->headers->get('upload-concat'));
        $this->assertEquals('1.0.0', $response->headers->get('tus-resumable'));
        $this->assertEquals('no-store, private', $response->headers->get('cache-control'));
    }

    /**
     * @test
     *
     * @covers ::handlePost
     */
    public function it_returns_400_for_invalid_upload()
    {
        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->add([
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/files',
            ]);

        $response = $this->tusServerMock->handlePost();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertNull($response->getOriginalContent());
    }

    /**
     * @test
     *
     * @covers ::handlePost
     */
    public function it_calls_concatenation_for_final_upload()
    {
        $fileName  = 'file.txt';
        $checksum  = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $uploadDir = '/path/to/uploads';

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->add([
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/files',
            ]);

        $requestMock = m::mock(Request::class, ['file'])->makePartial();
        $requestMock
            ->getRequest()
            ->headers
            ->add([
                'Upload-Metadata' => 'filename ' . base64_encode($fileName),
                'Checksum' => $checksum,
                'Upload-Length' => 10,
                'Upload-Concat' => 'final;/files/a /files/b',
            ]);

        $requestMock
            ->getRequest()
            ->server
            ->add([
                'SERVER_NAME' => 'tus.local',
                'SERVER_PORT' => 80,
            ]);

        $this->tusServerMock
            ->shouldReceive('getRequest')
            ->times(3)
            ->andReturn($requestMock);

        $this->tusServerMock->setUploadDir($uploadDir);

        $this->tusServerMock
            ->shouldReceive('handleConcatenation')
            ->with($fileName, $uploadDir . '/' . $fileName)
            ->once()
            ->andReturn(new HttpResponse);

        $this->assertInstanceOf(HttpResponse::class, $this->tusServerMock->handlePost());
    }

    /**
     * @test
     *
     * @covers ::handlePost
     */
    public function it_handles_post_for_partial_request()
    {
        $baseDir   = __DIR__ . '/../.tmp';
        $fileName  = 'file.txt';
        $checksum  = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a_partial';
        $folder    = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $location  = 'http://tus.local/.tmp/file.txt';
        $expiresAt = 'Sat, 09 Dec 2017 00:00:00 GMT';

        $this->tusServerMock->setUploadDir($baseDir);
        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->add([
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/files',
            ]);

        $requestMock = m::mock(Request::class, ['file'])->makePartial();
        $requestMock
            ->getRequest()
            ->headers
            ->add([
                'Upload-Length' => 10,
                'Upload-Checksum' => $checksum,
                'Upload-Metadata' => 'filename ' . base64_encode($fileName),
                'Upload-Concat' => 'partial',
            ]);

        $requestMock
            ->getRequest()
            ->server
            ->add([
                'SERVER_NAME' => 'tus.local',
                'SERVER_PORT' => 80,
            ]);

        $this->tusServerMock
            ->shouldReceive('getRequest')
            ->times(5)
            ->andReturn($requestMock);

        $this->tusServerMock
            ->shouldReceive('getUploadChecksum')
            ->once()
            ->andReturn($checksum);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with($checksum)
            ->andReturn([
                'expires_at' => $expiresAt,
            ]);

        $cacheMock
            ->shouldReceive('getTtl')
            ->once()
            ->andReturn(86400);

        $cacheMock
            ->shouldReceive('set')
            ->once()
            ->with($checksum, [
                'name' => $fileName,
                'size' => 10,
                'offset' => 0,
                'file_path' => "$baseDir/$folder/$fileName",
                'location' => $location,
                'created_at' => 'Fri, 08 Dec 2017 00:00:00 GMT',
                'expires_at' => $expiresAt,
                'upload_type' => 'partial',
            ])
            ->andReturn(null);

        $this->tusServerMock->setCache($cacheMock);
        $this->tusServerMock->getResponse()->createOnly(true);

        $response = $this->tusServerMock->handlePost();

        $this->assertEquals([
            'data' => [
                'checksum' => $checksum,
            ],
        ], $response->getOriginalContent());

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals($location, $response->headers->get('location'));
        $this->assertEquals($expiresAt, $response->headers->get('upload-expires'));
        $this->assertEquals('1.0.0', $response->headers->get('tus-resumable'));
    }

    /**
     * @test
     *
     * @covers ::handlePost
     */
    public function it_handles_post_request()
    {
        $fileName  = 'file.txt';
        $checksum  = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $location  = 'http://tus.local/uploads/file.txt';
        $expiresAt = 'Sat, 09 Dec 2017 00:00:00 GMT';

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->add([
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/files',
            ]);

        $requestMock = m::mock(Request::class, ['file'])->makePartial();
        $requestMock
            ->getRequest()
            ->headers
            ->add([
                'Upload-Metadata' => 'filename ' . base64_encode($fileName),
                'Upload-Checksum' => $checksum,
                'Upload-Length' => 10,
            ]);

        $requestMock
            ->getRequest()
            ->server
            ->add([
                'SERVER_NAME' => 'tus.local',
                'SERVER_PORT' => 80,
            ]);

        $this->tusServerMock
            ->shouldReceive('getRequest')
            ->times(5)
            ->andReturn($requestMock);

        $this->tusServerMock
            ->shouldReceive('getUploadChecksum')
            ->once()
            ->andReturn($checksum);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with($checksum)
            ->andReturn([
                'expires_at' => $expiresAt,
            ]);

        $cacheMock
            ->shouldReceive('getTtl')
            ->once()
            ->andReturn(86400);

        $cacheMock
            ->shouldReceive('set')
            ->once()
            ->with($checksum, [
                'name' => $fileName,
                'size' => 10,
                'offset' => 0,
                'file_path' => dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $fileName,
                'location' => $location,
                'created_at' => 'Fri, 08 Dec 2017 00:00:00 GMT',
                'expires_at' => $expiresAt,
                'upload_type' => 'normal',
            ])
            ->andReturn(null);

        $this->tusServerMock->setCache($cacheMock);
        $this->tusServerMock->getResponse()->createOnly(true);

        $response = $this->tusServerMock->handlePost();

        $this->assertEquals([
            'data' => [
                'checksum' => $checksum,
            ],
        ], $response->getOriginalContent());

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals($location, $response->headers->get('location'));
        $this->assertEquals($expiresAt, $response->headers->get('upload-expires'));
        $this->assertEquals('1.0.0', $response->headers->get('tus-resumable'));
    }

    /**
     * @test
     *
     * @covers ::handleConcatenation
     */
    public function it_handles_concatenation_request()
    {
        $fileName = 'file.txt';
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $filePath = __DIR__ . '/../.tmp/';
        $location = 'http://tus.local/uploads/file.txt';

        $files = [
            ['file_path' => $filePath . 'file_a', 'offset' => 10],
            ['file_path' => $filePath . 'file_b', 'offset' => 20],
        ];

        $concatenatedFile = [
            'name' => $fileName,
            'offset' => 0,
            'size' => 0,
            'file_path' => $filePath . $fileName,
            'location' => $location,
        ];

        $requestMock = m::mock(Request::class, ['file'])->makePartial();
        $requestMock
            ->getRequest()
            ->headers
            ->add([
                'Upload-Length' => 10,
                'Upload-Checksum' => 'sha256 ' . base64_encode($checksum),
                'Upload-Concat' => 'final;file_a file_b',
                'Upload-Metadata' => 'filename ' . base64_encode($fileName),
            ]);

        $requestMock
            ->getRequest()
            ->server
            ->add([
                'SERVER_NAME' => 'tus.local',
                'SERVER_PORT' => 80,
            ]);

        $this->tusServerMock
            ->shouldReceive('getRequest')
            ->times(3)
            ->andReturn($requestMock);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with('file_a')
            ->andReturn([
                'file_path' => $filePath . 'file_a',
                'offset' => 10,
            ]);

        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with('file_b')
            ->andReturn([
                'file_path' => $filePath . 'file_b',
                'offset' => 20,
            ]);

        $cacheMock
            ->shouldReceive('set')
            ->once()
            ->with($checksum, $concatenatedFile + ['upload_type' => 'final'])
            ->andReturnNull();

        $cacheMock
            ->shouldReceive('deleteAll')
            ->once()
            ->with(['file_a', 'file_b'])
            ->andReturn(true);

        $this->tusServerMock->setCache($cacheMock);
        $this->tusServerMock->getResponse()->createOnly(true);

        $fileMock = m::mock(File::class, [$fileName, $cacheMock]);

        $this->tusServerMock
            ->shouldReceive('buildFile')
            ->once()
            ->with($concatenatedFile)
            ->andReturn($fileMock);

        $this->tusServerMock
            ->shouldReceive('getChecksum')
            ->once()
            ->with($filePath . $fileName)
            ->andReturn($checksum);

        $fileMock
            ->shouldReceive('setFilePath')
            ->once()
            ->with($filePath . $fileName)
            ->andReturnSelf();

        $fileMock
            ->shouldReceive('setOffset')
            ->once()
            ->with(30)
            ->andReturnSelf();

        $fileMock
            ->shouldReceive('merge')
            ->once()
            ->with($files)
            ->andReturn(30);

        $fileMock
            ->shouldReceive('details')
            ->once()
            ->andReturn($concatenatedFile);

        $fileMock
            ->shouldReceive('delete')
            ->once()
            ->with([
                $filePath . 'file_a',
                $filePath . 'file_b',
            ], true)
            ->andReturn(true);

        $response = $this->tusServerMock->handleConcatenation($fileName, $filePath . $fileName);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals($location, $response->headers->get('location'));
        $this->assertEquals('1.0.0', $response->headers->get('tus-resumable'));
    }

    /**
     * @test
     *
     * @covers ::handleConcatenation
     */
    public function it_throws_460_for_checksum_mismatch_in_concatenation_request()
    {
        $fileName = 'file.txt';
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $filePath = __DIR__ . '/../.tmp/';
        $location = 'http://tus.local/uploads/file.txt';
        $files    = [
            ['file_path' => $filePath . 'file_a', 'offset' => 10],
            ['file_path' => $filePath . 'file_b', 'offset' => 20],
        ];

        $requestMock = m::mock(Request::class, ['file'])->makePartial();
        $requestMock
            ->getRequest()
            ->headers
            ->add([
                'Upload-Length' => 10,
                'Upload-Checksum' => 'sha256 ' . base64_encode('invalid'),
                'Upload-Concat' => 'final;file_a file_b',
                'Upload-Metadata' => 'filename ' . base64_encode($fileName),
            ]);

        $requestMock
            ->getRequest()
            ->server
            ->add([
                'SERVER_NAME' => 'tus.local',
                'SERVER_PORT' => 80,
            ]);

        $this->tusServerMock
            ->shouldReceive('getRequest')
            ->times(3)
            ->andReturn($requestMock);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with('file_a')
            ->andReturn([
                'file_path' => $filePath . 'file_a',
                'offset' => 10,
            ]);

        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with('file_b')
            ->andReturn([
                'file_path' => $filePath . 'file_b',
                'offset' => 20,
            ]);

        $this->tusServerMock->setCache($cacheMock);
        $this->tusServerMock->getResponse()->createOnly(true);

        $fileMock = m::mock(File::class, [$fileName, $cacheMock]);

        $this->tusServerMock
            ->shouldReceive('buildFile')
            ->once()
            ->with([
                'name' => $fileName,
                'offset' => 0,
                'size' => 0,
                'file_path' => $filePath . $fileName,
                'location' => $location,
            ])
            ->andReturn($fileMock);

        $this->tusServerMock
            ->shouldReceive('getChecksum')
            ->once()
            ->with($filePath . $fileName)
            ->andReturn($checksum);

        $fileMock
            ->shouldReceive('setFilePath')
            ->once()
            ->with($filePath . $fileName)
            ->andReturnSelf();

        $fileMock
            ->shouldReceive('setOffset')
            ->once()
            ->with(30)
            ->andReturnSelf();

        $fileMock
            ->shouldReceive('merge')
            ->once()
            ->with($files)
            ->andReturn(30);

        $response = $this->tusServerMock->handleConcatenation($fileName, $filePath . $fileName);

        $this->assertEquals(460, $response->getStatusCode());
        $this->assertNull($response->getOriginalContent());
    }

    /**
     * @test
     *
     * @covers ::handlePatch
     */
    public function it_returns_410_for_invalid_patch_request()
    {
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->add([
                'REQUEST_METHOD' => 'PATCH',
                'REQUEST_URI' => '/files/' . $checksum,
            ]);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with($checksum)
            ->andReturn(null);

        $this->tusServerMock->setCache($cacheMock);

        $response = $this->tusServerMock->handlePatch();

        $this->assertEquals(410, $response->getStatusCode());
        $this->assertNull($response->getOriginalContent());
    }

    /**
     * @test
     *
     * @covers ::handlePatch
     */
    public function it_returns_422_for_file_exception()
    {
        $fileName  = 'file.txt';
        $fileSize  = 1024;
        $checksum  = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $location  = 'http://tus.local/uploads/file.txt';
        $expiresAt = 'Sat, 09 Dec 2017 00:00:00 GMT';
        $fileMeta  = [
            'name' => $fileName,
            'size' => $fileSize,
            'offset' => 0,
            'file_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR . $fileName,
            'location' => $location,
            'created_at' => 'Fri, 08 Dec 2017 00:00:00 GMT',
            'expires_at' => $expiresAt,
            'upload_type' => 'normal',
        ];

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->add([
                'REQUEST_METHOD' => 'PATCH',
                'REQUEST_URI' => '/files/' . $checksum,
            ]);

        $fileMock = m::mock(File::class);
        $fileMock
            ->shouldReceive('setChecksum')
            ->once()
            ->with($checksum)
            ->andReturnSelf();

        $fileMock
            ->shouldReceive('getFileSize')
            ->once()
            ->andReturn($fileSize);

        $fileMock
            ->shouldReceive('upload')
            ->once()
            ->with($fileSize)
            ->andThrow(new FileException('Unable to open file.'));

        $this->tusServerMock
            ->shouldReceive('buildFile')
            ->once()
            ->with($fileMeta)
            ->andReturn($fileMock);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with($checksum)
            ->andReturn($fileMeta);

        $this->tusServerMock->setCache($cacheMock);
        $this->tusServerMock->getResponse()->createOnly(true);

        $response = $this->tusServerMock->handlePatch();

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('Unable to open file.', $response->getOriginalContent());
    }

    /**
     * @test
     *
     * @covers ::handlePatch
     */
    public function it_returns_416_for_corrupt_upload()
    {
        $fileName  = 'file.txt';
        $fileSize  = 1024;
        $checksum  = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $location  = 'http://tus.local/uploads/file.txt';
        $expiresAt = 'Sat, 09 Dec 2017 00:00:00 GMT';
        $fileMeta  = [
            'name' => $fileName,
            'size' => $fileSize,
            'offset' => 0,
            'file_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR . $fileName,
            'location' => $location,
            'created_at' => 'Fri, 08 Dec 2017 00:00:00 GMT',
            'expires_at' => $expiresAt,
            'upload_type' => 'normal',
        ];

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->add([
                'REQUEST_METHOD' => 'PATCH',
                'REQUEST_URI' => '/files/' . $checksum,
            ]);

        $fileMock = m::mock(File::class);
        $fileMock
            ->shouldReceive('setChecksum')
            ->once()
            ->with($checksum)
            ->andReturnSelf();

        $fileMock
            ->shouldReceive('getFileSize')
            ->once()
            ->andReturn($fileSize);

        $fileMock
            ->shouldReceive('upload')
            ->once()
            ->with($fileSize)
            ->andThrow(new OutOfRangeException('The uploaded file is corrupt.'));

        $this->tusServerMock
            ->shouldReceive('buildFile')
            ->once()
            ->with($fileMeta)
            ->andReturn($fileMock);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with($checksum)
            ->andReturn($fileMeta);

        $this->tusServerMock->setCache($cacheMock);
        $this->tusServerMock->getResponse()->createOnly(true);

        $response = $this->tusServerMock->handlePatch();

        $this->assertEquals(416, $response->getStatusCode());
        $this->assertNull($response->getOriginalContent());
    }

    /**
     * @test
     *
     * @covers ::handlePatch
     */
    public function it_returns_100_for_aborted_upload()
    {
        $fileName  = 'file.txt';
        $fileSize  = 1024;
        $checksum  = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $location  = 'http://tus.local/uploads/file.txt';
        $expiresAt = 'Sat, 09 Dec 2017 00:00:00 GMT';
        $fileMeta  = [
            'name' => $fileName,
            'size' => $fileSize,
            'offset' => 0,
            'file_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR . $fileName,
            'location' => $location,
            'created_at' => 'Fri, 08 Dec 2017 00:00:00 GMT',
            'expires_at' => $expiresAt,
            'upload_type' => 'normal',
        ];

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->add([
                'REQUEST_METHOD' => 'PATCH',
                'REQUEST_URI' => '/files/' . $checksum,
            ]);

        $fileMock = m::mock(File::class);
        $fileMock
            ->shouldReceive('setChecksum')
            ->once()
            ->with($checksum)
            ->andReturnSelf();

        $fileMock
            ->shouldReceive('getFileSize')
            ->once()
            ->andReturn($fileSize);

        $fileMock
            ->shouldReceive('upload')
            ->once()
            ->with($fileSize)
            ->andThrow(new ConnectionException);

        $this->tusServerMock
            ->shouldReceive('buildFile')
            ->once()
            ->with($fileMeta)
            ->andReturn($fileMock);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with($checksum)
            ->andReturn($fileMeta);

        $this->tusServerMock->setCache($cacheMock);
        $this->tusServerMock->getResponse()->createOnly(true);

        $response = $this->tusServerMock->handlePatch();

        $this->assertEquals(100, $response->getStatusCode());
        $this->assertNull($response->getOriginalContent());
    }

    /**
     * @test
     *
     * @covers ::handlePatch
     */
    public function it_returns_403_for_patch_request_against_final_upload()
    {
        $fileName  = 'file.txt';
        $fileSize  = 1024;
        $checksum  = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $location  = 'http://tus.local/uploads/file.txt';
        $expiresAt = 'Sat, 09 Dec 2017 00:00:00 GMT';
        $fileMeta  = [
            'name' => $fileName,
            'size' => $fileSize,
            'offset' => 0,
            'file_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR . $fileName,
            'location' => $location,
            'created_at' => 'Fri, 08 Dec 2017 00:00:00 GMT',
            'expires_at' => $expiresAt,
            'upload_type' => 'final',
        ];

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->add([
                'REQUEST_METHOD' => 'PATCH',
                'REQUEST_URI' => '/files/' . $checksum,
            ]);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with($checksum)
            ->andReturn($fileMeta);

        $this->tusServerMock->setCache($cacheMock);
        $this->tusServerMock->getResponse()->createOnly(true);

        $response = $this->tusServerMock->handlePatch();

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertNull($response->getOriginalContent());
    }

    /**
     * @test
     *
     * @covers ::handlePatch
     */
    public function it_returns_460_for_corrupt_upload()
    {
        $fileName  = 'file.txt';
        $fileSize  = 1024;
        $checksum  = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $location  = 'http://tus.local/uploads/file.txt';
        $expiresAt = 'Sat, 09 Dec 2017 00:00:00 GMT';
        $fileMeta  = [
            'name' => $fileName,
            'size' => $fileSize,
            'offset' => 0,
            'file_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR . $fileName,
            'location' => $location,
            'created_at' => 'Fri, 08 Dec 2017 00:00:00 GMT',
            'expires_at' => $expiresAt,
            'upload_type' => 'normal',
        ];

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->add([
                'REQUEST_METHOD' => 'PATCH',
                'REQUEST_URI' => '/files/' . $checksum,
            ]);

        $this->tusServerMock
            ->shouldReceive('getUploadChecksum')
            ->once()
            ->andReturn('invalid');

        $fileMock = m::mock(File::class);
        $fileMock
            ->shouldReceive('setChecksum')
            ->once()
            ->with($checksum)
            ->andReturnSelf();

        $fileMock
            ->shouldReceive('getFileSize')
            ->once()
            ->andReturn($fileSize);

        $fileMock
            ->shouldReceive('upload')
            ->once()
            ->with($fileSize)
            ->andReturn($fileSize);

        $this->tusServerMock
            ->shouldReceive('buildFile')
            ->once()
            ->with($fileMeta)
            ->andReturn($fileMock);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with($checksum)
            ->andReturn($fileMeta);

        $this->tusServerMock->setCache($cacheMock);
        $this->tusServerMock->getResponse()->createOnly(true);

        $response = $this->tusServerMock->handlePatch();

        $this->assertEquals(460, $response->getStatusCode());
        $this->assertNull($response->getOriginalContent());
    }

    /**
     * @test
     *
     * @covers ::handlePatch
     */
    public function it_handles_patch_request()
    {
        $fileName  = 'file.txt';
        $fileSize  = 1024;
        $checksum  = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $location  = 'http://tus.local/uploads/file.txt';
        $expiresAt = 'Sat, 09 Dec 2017 00:00:00 GMT';
        $fileMeta  = [
            'name' => $fileName,
            'size' => $fileSize,
            'offset' => 0,
            'file_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR . $fileName,
            'location' => $location,
            'created_at' => 'Fri, 08 Dec 2017 00:00:00 GMT',
            'expires_at' => $expiresAt,
            'upload_type' => 'normal',
        ];

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->add([
                'REQUEST_METHOD' => 'PATCH',
                'REQUEST_URI' => '/files/' . $checksum,
            ]);

        $fileMock = m::mock(File::class);
        $fileMock
            ->shouldReceive('setChecksum')
            ->once()
            ->with($checksum)
            ->andReturnSelf();

        $fileMock
            ->shouldReceive('getFileSize')
            ->once()
            ->andReturn($fileSize);

        $fileMock
            ->shouldReceive('upload')
            ->once()
            ->with($fileSize)
            ->andReturn(100);

        $this->tusServerMock
            ->shouldReceive('buildFile')
            ->once()
            ->with($fileMeta)
            ->andReturn($fileMock);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->twice()
            ->with($checksum)
            ->andReturn($fileMeta);

        $this->tusServerMock->setCache($cacheMock);
        $this->tusServerMock->getResponse()->createOnly(true);

        $response = $this->tusServerMock->handlePatch();

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals($expiresAt, $response->headers->get('upload-expires'));
        $this->assertEquals(100, $response->headers->get('upload-offset'));
        $this->assertEquals('1.0.0', $response->headers->get('tus-resumable'));
        $this->assertNull($response->getOriginalContent());
    }

    /**
     * @test
     *
     * @covers ::handleGet
     */
    public function it_returns_400_for_request_without_hash()
    {
        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->add([
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/files',
            ]);

        $this->tusServerMock->getResponse()->createOnly(true);

        $response = $this->tusServerMock->handleGet();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('400 bad request.', $response->getOriginalContent());
    }

    /**
     * @test
     *
     * @covers ::handleGet
     */
    public function it_returns_404_for_invalid_get_request()
    {
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->add([
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/files/' . $checksum,
            ]);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with($checksum)
            ->andReturn([]);

        $this->tusServerMock->setCache($cacheMock);
        $this->tusServerMock->getResponse()->createOnly(true);

        $response = $this->tusServerMock->handleGet();

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('404 upload not found.', $response->getOriginalContent());
    }

    /**
     * @test
     *
     * @covers ::handleGet
     */
    public function it_returns_404_if_resource_doesnt_exist()
    {
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->add([
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/files/' . $checksum,
            ]);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with($checksum)
            ->andReturn([
                'file_path' => '/path/to/invalid/file.txt',
            ]);

        $this->tusServerMock->setCache($cacheMock);
        $this->tusServerMock->getResponse()->createOnly(true);

        $response = $this->tusServerMock->handleGet();

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('404 upload not found.', $response->getOriginalContent());
    }

    /**
     * @test
     *
     * @covers ::handleGet
     */
    public function it_handles_get_request()
    {
        $fileName  = 'file.txt';
        $fileSize  = 1024;
        $checksum  = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $location  = 'http://tus.local/uploads/file.txt';
        $expiresAt = 'Sat, 09 Dec 2017 00:00:00 GMT';
        $fileMeta  = [
            'name' => $fileName,
            'size' => $fileSize,
            'offset' => 0,
            'file_path' => __DIR__ . '/../Fixtures/empty.txt',
            'location' => $location,
            'created_at' => 'Fri, 08 Dec 2017 00:00:00 GMT',
            'expires_at' => $expiresAt,
        ];

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->add([
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/files/' . $checksum,
            ]);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with($checksum)
            ->andReturn($fileMeta);

        $this->tusServerMock->setCache($cacheMock);
        $this->tusServerMock->getResponse()->createOnly(true);

        $response = $this->tusServerMock->handleGet();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals("attachment; filename=\"$fileName\"", $response->headers->get('content-disposition'));
    }

    /**
     * @test
     *
     * @covers ::handleDelete
     */
    public function it_returns_404_for_invalid_resource_in_delete_request()
    {
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->add([
                'REQUEST_METHOD' => 'DELETE',
                'REQUEST_URI' => '/files/' . $checksum,
            ]);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with($checksum)
            ->andReturn(null);

        $this->tusServerMock->setCache($cacheMock);
        $this->tusServerMock->getResponse()->createOnly(true);

        $response = $this->tusServerMock->handleDelete();

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertNull($response->getOriginalContent());
    }

    /**
     * @test
     *
     * @covers ::handleDelete
     */
    public function it_returns_410_for_invalid_resource_in_delete_request()
    {
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->add([
                'REQUEST_METHOD' => 'DELETE',
                'REQUEST_URI' => '/files/' . $checksum,
            ]);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with($checksum)
            ->andReturn([
                'file_path' => __DIR__ . '/../Fixtures/empty.txt',
            ]);

        $cacheMock
            ->shouldReceive('delete')
            ->once()
            ->with($checksum)
            ->andReturn(false);

        $this->tusServerMock->setCache($cacheMock);
        $this->tusServerMock->getResponse()->createOnly(true);

        $response = $this->tusServerMock->handleDelete();

        $this->assertEquals(410, $response->getStatusCode());
        $this->assertNull($response->getOriginalContent());
    }

    /**
     * @test
     *
     * @covers ::handleDelete
     */
    public function it_handles_delete_request()
    {
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->server
            ->add([
                'REQUEST_METHOD' => 'DELETE',
                'REQUEST_URI' => '/files/' . $checksum,
            ]);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with($checksum)
            ->andReturn([
                'file_path' => __DIR__ . '/../Fixtures/empty.txt',
            ]);

        $cacheMock
            ->shouldReceive('delete')
            ->once()
            ->with($checksum)
            ->andReturn(true);

        $this->tusServerMock->setCache($cacheMock);
        $this->tusServerMock->getResponse()->createOnly(true);

        $mockBuilder = (new MockBuilder())->setNamespace('\TusPhp\Tus');

        $mockBuilder
            ->setName('unlink')
            ->setFunction(
                function () {
                    return true;
                }
            );

        $mock = $mockBuilder->build();

        $mock->enable();

        $response = $this->tusServerMock->handleDelete();

        $this->assertNull($response->getOriginalContent());
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals('1.0.0', $response->headers->get('tus-resumable'));
        $this->assertEquals('termination', $response->headers->get('tus-extension'));

        $mock->disable();
    }

    /**
     * @test
     *
     * @covers ::buildFile
     */
    public function it_builds_file_object_from_meta()
    {
        $fileName  = 'file.txt';
        $fileSize  = 1024;
        $filePath  = dirname(__DIR__) . DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR . $fileName;
        $location  = 'http://tus.local/uploads/file.txt';
        $createdAt = 'Fri, 08 Dec 2017 00:00:00 GMT';
        $expiresAt = 'Sat, 09 Dec 2017 00:00:00 GMT';

        $tusServerMock = m::mock(TusServer::class)
                          ->shouldAllowMockingProtectedMethods()
                          ->makePartial();

        $tusServerMock->setCache('file');

        $file = $tusServerMock->buildFile([
            'name' => $fileName,
            'size' => $fileSize,
            'offset' => 0,
            'file_path' => $filePath,
            'location' => $location,
            'created_at' => $createdAt,
            'expires_at' => $expiresAt,
        ]);

        $details = $file->details();

        $this->assertInstanceOf(File::class, $file);
        $this->assertEquals($fileName, $details['name']);
        $this->assertEquals($fileSize, $details['size']);
        $this->assertEquals(0, $details['offset']);
        $this->assertEquals($filePath, $details['file_path']);
        $this->assertEquals($location, $details['location']);
        $this->assertEquals($createdAt, $details['created_at']);
        $this->assertEquals($expiresAt, $details['expires_at']);
    }

    /**
     * @test
     *
     * @runInSeparateProcess
     *
     * @covers ::getSupportedHashAlgorithms
     */
    public function it_gets_supported_hash_algorithms()
    {
        $mockBuilder = (new MockBuilder())->setNamespace('\TusPhp\Tus');

        $mockBuilder
            ->setName('hash_algos')
            ->setFunction(
                function () {
                    return ['md5', 'sha1', 'sha256', 'haval256,3'];
                }
            );

        $mock = $mockBuilder->build();

        $mock->enable();

        $this->assertEquals("md5,sha1,sha256,'haval256,3'", $this->tusServerMock->getSupportedHashAlgorithms());

        $mock->disable();
    }

    /**
     * @test
     *
     * @covers ::getUploadChecksum
     */
    public function it_returns_400_if_upload_checksum_header_is_not_present()
    {
        $response = $this->tusServerMock->getUploadChecksum();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertNull($response->getOriginalContent());
    }

    /**
     * @test
     *
     * @runInSeparateProcess
     *
     * @covers ::getUploadChecksum
     */
    public function it_returns_404_for_unsupported_hash_algorithm()
    {
        $filePath    = __DIR__ . '/../Fixtures/empty.txt';
        $checksum    = hash_file('sha1', $filePath);
        $mockBuilder = (new MockBuilder())->setNamespace('\TusPhp\Tus');

        $mockBuilder
            ->setName('hash_algos')
            ->setFunction(
                function () {
                    return ['invalid'];
                }
            );

        $mock = $mockBuilder->build();

        $mock->enable();

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->headers
            ->set('Upload-Checksum', 'sha1 ' . base64_encode($checksum));

        $response = $this->tusServerMock->getUploadChecksum();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertNull($response->getOriginalContent());

        $mock->disable();
    }

    /**
     * @test
     *
     * @runInSeparateProcess
     *
     * @covers ::getUploadChecksum
     */
    public function it_returns_404_for_invalid_checksum_in_header()
    {
        $mockBuilder = (new MockBuilder())->setNamespace('\TusPhp\Tus');

        $mockBuilder
            ->setName('base64_decode')
            ->setFunction(
                function () {
                    return false;
                }
            );

        $mock = $mockBuilder->build();

        $mock->enable();

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->headers
            ->set('Upload-Checksum', 'sha1 invalid');

        $response = $this->tusServerMock->getUploadChecksum();

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertNull($response->getOriginalContent());

        $mock->disable();
    }

    /**
     * @test
     *
     * @covers ::getUploadChecksum
     */
    public function it_gets_upload_checksum_from_header()
    {
        $filePath = __DIR__ . '/../Fixtures/empty.txt';
        $checksum = hash_file('sha1', $filePath);

        $this->tusServerMock
            ->getRequest()
            ->getRequest()
            ->headers
            ->set('Upload-Checksum', 'sha1 ' . base64_encode($checksum));

        $this->assertEquals($checksum, $this->tusServerMock->getUploadChecksum());
    }

    /**
     * @test
     *
     * @covers ::isExpired
     */
    public function it_checks_expiry_date()
    {
        $this->assertFalse($this->tusServerMock->isExpired(['expires_at' => 'Sat, 09 Dec 2017 00:00:00 GMT']));

        $this->assertFalse($this->tusServerMock->isExpired([
            'expires_at' => 'Thu, 07 Dec 2017 00:00:00 GMT',
            'offset' => 100,
            'size' => 100,
            'file_path' => '/path/to/file.txt',
        ]));

        $this->assertTrue($this->tusServerMock->isExpired([
            'expires_at' => 'Thu, 07 Dec 2017 00:00:00 GMT',
            'offset' => 100,
            'size' => 1100,
        ]));
    }

    /**
     * @test
     *
     * @covers ::isExpired
     * @covers ::handleExpiration
     */
    public function it_deletes_expired_uploads()
    {
        $filePath  = __DIR__ . '/../.tmp/upload.txt';
        $cacheMock = m::mock(FileStore::class);

        $cacheMock
            ->shouldReceive('keys')
            ->once()
            ->andReturn([
                'expired_false',
                'expired_but_uploaded',
                'expired_true',
            ]);

        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with('expired_false', true)
            ->andReturn(['expires_at' => 'Sat, 09 Dec 2017 00:00:00 GMT']);

        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with('expired_but_uploaded', true)
            ->andReturn([
                'expires_at' => 'Thu, 07 Dec 2017 00:00:00 GMT',
                'offset' => 100,
                'size' => 100,
            ]);

        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with('expired_true', true)
            ->andReturn([
                'expires_at' => 'Thu, 07 Dec 2017 00:00:00 GMT',
                'offset' => 100,
                'size' => 1100,
                'file_path' => $filePath,
            ]);

        $cacheMock
            ->shouldReceive('delete')
            ->with('expired_true')
            ->once()
            ->andReturn(true);

        $this->tusServerMock->setCache($cacheMock);

        touch($filePath);

        $this->assertTrue(file_exists($filePath));

        $this->assertEquals([
            [
                'expires_at' => 'Thu, 07 Dec 2017 00:00:00 GMT',
                'offset' => 100,
                'size' => 1100,
                'file_path' => $filePath,
            ],
        ], $this->tusServerMock->handleExpiration());

        $this->assertFalse(file_exists($filePath));
    }

    /**
     * @test
     *
     * @covers ::isExpired
     * @covers ::handleExpiration
     */
    public function it_doesnt_unlink_if_unable_to_delete_from_cache()
    {
        $filePath  = dirname(__DIR__) . DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR . 'empty.txt';
        $cacheMock = m::mock(FileStore::class);

        $cacheMock
            ->shouldReceive('keys')
            ->once()
            ->andReturn(['expired_true']);

        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with('expired_true', true)
            ->andReturn([
                'expires_at' => 'Thu, 07 Dec 2017 00:00:00 GMT',
                'offset' => 100,
                'size' => 1100,
                'file_path' => $filePath,
            ]);

        $cacheMock
            ->shouldReceive('delete')
            ->with('expired_true')
            ->once()
            ->andReturn(false);

        $this->tusServerMock->setCache($cacheMock);

        $this->assertEquals([], $this->tusServerMock->handleExpiration());
    }

    /**
     * @test
     *
     * @covers ::setUploadDir
     * @covers ::getPathForPartialUpload
     */
    public function it_gets_path_for_partial_upload()
    {
        $baseDir   = __DIR__ . '/../.tmp';
        $uploadDir = $baseDir . '/checksum/';

        $this->tusServerMock->setUploadDir($baseDir);

        $this->assertEquals(
            $uploadDir,
            $this->tusServerMock->getPathForPartialUpload('checksum_partial')
        );

        $this->assertTrue(file_exists($uploadDir));
        $this->assertTrue(is_dir($uploadDir));

        @rmdir($uploadDir);
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

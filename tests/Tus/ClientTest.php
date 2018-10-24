<?php

namespace TusPhp\Test;

use Mockery as m;
use GuzzleHttp\Client;
use phpmock\MockBuilder;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use TusPhp\Tus\Client as TusClient;
use TusPhp\Exception\FileException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;

/**
 * @coversDefaultClass \TusPhp\Tus\Client
 */
class ClientTest extends TestCase
{
    /** @var TusClient */
    protected $tusClient;

    /** @var \Mockery Mock. */
    protected $tusClientMock;

    /**
     * Prepare vars.
     *
     * @return void
     */
    public function setUp()
    {
        $this->tusClient     = new TusClient('http://tus.local');
        $this->tusClientMock = m::mock(TusClient::class)
                                ->shouldAllowMockingProtectedMethods()
                                ->makePartial();

        parent::setUp();
    }

    /**
     * @test
     *
     * @covers ::file
     *
     * @expectedException \TusPhp\Exception\FileException
     * @expectedExceptionMessageRegExp /Cannot read file: [a-zA-Z0-9-\/.]+/
     */
    public function it_throws_exception_for_invalid_file()
    {
        $this->tusClient->file('/path/to/invalid/file.txt');
    }

    /**
     * @test
     *
     * @covers ::file
     *
     * @runInSeparateProcess
     *
     * @expectedException \TusPhp\Exception\FileException
     * @expectedExceptionMessageRegExp /Cannot read file: [a-zA-Z0-9-\/.]+/
     */
    public function it_throws_exception_for_unreadable_file()
    {
        $file = __DIR__ . '/../Fixtures/403.txt';

        $mockBuilder = (new MockBuilder)->setNamespace('\TusPhp\Tus');

        $mockBuilder
            ->setName('is_readable')
            ->setFunction(
                function () {
                    return false;
                }
            );

        $mock = $mockBuilder->build();

        $mock->enable();

        $this->tusClient->file($file);

        $mock->disable();
    }

    /**
     * @test
     *
     * @covers ::file
     * @covers ::getFilePath
     * @covers ::getFileName
     * @covers ::getFileSize
     */
    public function it_sets_and_gets_file_attributes()
    {
        $file = __DIR__ . '/../Fixtures/data.txt';

        $this->tusClient->file($file);

        $this->assertEquals($file, $this->tusClient->getFilePath());
        $this->assertEquals('data.txt', $this->tusClient->getFileName());
        $this->assertEquals(filesize($file), $this->tusClient->getFileSize());
    }

    /**
     * @test
     *
     * @covers ::setFileName
     * @covers ::getFileName
     */
    public function it_sets_and_gets_filename()
    {
        $this->assertNull($this->tusClient->getFileName());
        $this->assertInstanceOf(TusClient::class, $this->tusClient->setFileName('file.txt'));
        $this->assertEquals('file.txt', $this->tusClient->getFileName());
    }

    /**
     * @test
     *
     * @covers ::__construct
     * @covers ::getClient
     */
    public function it_gets_client()
    {
        $this->assertInstanceOf(Client::class, $this->tusClient->getClient());
        $this->assertEquals('http://tus.local', $this->tusClient->getClient()->getConfig()['base_uri']);
    }

    /**
     * @test
     *
     * @covers ::__construct
     * @covers ::getClient
     */
    public function it_injects_options_in_client()
    {
        $headers = [
            'User-Agent' => 'testing/1.0',
            'Accept' => 'application/json',
            'Tus-Resumable' => '1.0.0',
        ];

        $tusClient = new TusClient('http://tus.local', [
            'connect_timeout' => 3.14,
            'allow_redirects' => false,
            'base_uri' => 'http://should-not-override',
            'headers' => $headers,
        ]);

        $guzzleConfig = $tusClient->getClient()->getConfig();

        $this->assertEquals('http://tus.local', $guzzleConfig['base_uri']);
        $this->assertEquals(3.14, $guzzleConfig['connect_timeout']);
        $this->assertFalse($guzzleConfig['allow_redirects']);
        $this->assertEquals($headers, $guzzleConfig['headers']);
    }

    /**
     * @test
     *
     * @covers ::getChecksum
     */
    public function it_sets_and_gets_checksum()
    {
        $file     = __DIR__ . '/../Fixtures/data.txt';
        $checksum = hash_file('sha256', $file);

        $this->tusClientMock
            ->shouldReceive('getFilePath')
            ->once()
            ->andReturn($file);

        $this->assertEquals($checksum, $this->tusClientMock->getChecksum());
    }

    /**
     * @test
     *
     * @covers ::setKey
     * @covers ::getKey
     */
    public function it_sets_and_gets_key()
    {
        $key = uniqid();

        $this->assertInstanceOf(TusClient::class, $this->tusClient->setKey($key));
        $this->assertEquals($key, $this->tusClient->getKey());
    }

    /**
     * @test
     *
     * @covers ::setChecksumAlgorithm
     * @covers ::getChecksumAlgorithm
     */
    public function it_sets_and_gets_checksum_algorithm()
    {
        $this->assertEquals('sha256', $this->tusClient->getChecksumAlgorithm());
        $this->assertInstanceOf(TusClient::class, $this->tusClient->setChecksumAlgorithm('crc32'));
        $this->assertEquals('crc32', $this->tusClient->getChecksumAlgorithm());
    }

    /**
     * @test
     *
     * @covers ::seek
     * @covers ::partial
     * @covers ::getPartialOffset
     * @covers ::isPartial
     */
    public function it_sets_and_gets_partial()
    {
        $key = uniqid();

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($key);

        $this->assertFalse($this->tusClientMock->isPartial());
        $this->assertInstanceOf(TusClient::class, $this->tusClientMock->seek(100));
        $this->assertTrue($this->tusClientMock->isPartial());
        $this->assertEquals(100, $this->tusClientMock->getPartialOffset());
    }

    /**
     * @test
     *
     * @covers ::isPartial
     * @covers ::partial
     */
    public function it_set_state_only_for_partial_equals_false()
    {
        $this->assertFalse($this->tusClientMock->isPartial());
        $this->assertNull($this->tusClientMock->partial(false));
        $this->assertFalse($this->tusClientMock->isPartial());
    }

    /**
     * @test
     *
     * @covers ::partial
     */
    public function it_generates_unique_key_for_partial_checksum()
    {
        $actualKey  = uniqid();
        $partialKey = $actualKey . '_partial';

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($partialKey);

        $this->assertNull($this->tusClientMock->partial());
        $this->assertTrue($this->tusClientMock->isPartial());
    }

    /**
     * @test
     *
     * @covers ::upload
     */
    public function it_uploads_a_file()
    {
        $key    = uniqid();
        $bytes  = 100;
        $offset = 0;

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($key);

        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->with($key)
            ->andReturn(0);

        $this->tusClientMock
            ->shouldReceive('sendPatchRequest')
            ->once()
            ->with($bytes, $offset)
            ->andReturn($bytes);

        $this->assertEquals($bytes, $this->tusClientMock->upload($bytes));
    }

    /**
     * @test
     *
     * @covers ::upload
     */
    public function it_uploads_full_file_if_size_is_not_given()
    {
        $key    = uniqid();
        $bytes  = 100;
        $offset = 0;

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($key);

        $this->tusClientMock
            ->shouldReceive('getFileSize')
            ->once()
            ->andReturn($bytes);

        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->with($key)
            ->andReturn(0);

        $this->tusClientMock
            ->shouldReceive('sendPatchRequest')
            ->once()
            ->with($bytes, $offset)
            ->andReturn($bytes);

        $this->assertEquals($bytes, $this->tusClientMock->upload());
    }

    /**
     * @test
     *
     * @covers ::upload
     */
    public function it_creates_and_then_uploads_a_file_in_file_exception()
    {
        $key    = uniqid();
        $bytes  = 100;
        $offset = 0;

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($key);

        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->with($key)
            ->andThrow(new FileException);

        $this->tusClientMock
            ->shouldReceive('create')
            ->once()
            ->andReturn($key);

        $this->tusClientMock
            ->shouldReceive('sendPatchRequest')
            ->once()
            ->with($bytes, $offset)
            ->andReturn($bytes);

        $this->assertEquals($bytes, $this->tusClientMock->upload($bytes));
    }

    /**
     * @test
     *
     * @covers ::upload
     */
    public function it_creates_and_then_uploads_a_file_in_client_exception()
    {
        $key    = uniqid();
        $bytes  = 100;
        $offset = 0;

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($key);

        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->with($key)
            ->andThrow(m::mock(ClientException::class));

        $this->tusClientMock
            ->shouldReceive('create')
            ->once()
            ->andReturn($key);

        $this->tusClientMock
            ->shouldReceive('sendPatchRequest')
            ->once()
            ->with($bytes, $offset)
            ->andReturn($bytes);

        $this->assertEquals($bytes, $this->tusClientMock->upload($bytes));
    }

    /**
     * @test
     *
     * @covers ::upload
     *
     * @expectedException \TusPhp\Exception\ConnectionException
     * @expectedExceptionMessage Couldn't connect to server.
     */
    public function it_throws_connection_exception_for_network_issues()
    {
        $key = uniqid();

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($key);

        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->with($key)
            ->andThrow(m::mock(ConnectException::class));

        $this->tusClientMock->upload(100);
    }

    /**
     * @test
     *
     * @covers ::getOffset
     */
    public function it_returns_false_for_file_exception_in_get_offset()
    {
        $key = uniqid();

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($key);

        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->with($key)
            ->andThrow(new FileException);

        $this->assertFalse($this->tusClientMock->getOffset($key));
    }

    /**
     * @test
     *
     * @covers ::getOffset
     */
    public function it_returns_false_for_client_exception_in_get_offset()
    {
        $key = uniqid();

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($key);

        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->with($key)
            ->andThrow(m::mock(ClientException::class));

        $this->assertFalse($this->tusClientMock->getOffset($key));
    }

    /**
     * @test
     *
     * @covers ::getOffset
     */
    public function it_gets_offset_for_partially_uploaded_resource()
    {
        $key = uniqid();

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($key);

        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->with($key)
            ->andReturn(100);

        $this->assertEquals(100, $this->tusClientMock->getOffset($key));
    }

    /**
     * @test
     *
     * @covers ::sendHeadRequest
     */
    public function it_sends_a_head_request()
    {
        $checksum     = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);

        $guzzleMock
            ->shouldReceive('head')
            ->once()
            ->with('/files/' . $checksum)
            ->andReturn($responseMock);

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(200);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $responseMock
            ->shouldReceive('getHeader')
            ->once()
            ->with('upload-offset')
            ->andReturn([100]);

        $this->assertEquals(100, $this->tusClientMock->sendHeadRequest($checksum));
    }

    /**
     * @test
     *
     * @covers ::sendHeadRequest
     *
     * @expectedException \TusPhp\Exception\FileException
     * @expectedExceptionMessage File not found.
     */
    public function it_throws_file_exception_in_head_request()
    {
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);

        $guzzleMock
            ->shouldReceive('head')
            ->once()
            ->with('/files/' . $checksum)
            ->andReturn($responseMock);

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(404);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $this->tusClientMock->sendHeadRequest($checksum);
    }

    /**
     * @test
     *
     * @covers ::sendPatchRequest
     *
     * @expectedException \TusPhp\Exception\FileException
     * @expectedExceptionMessage The uploaded file is corrupt.
     */
    public function it_throws_file_exception_for_corrupt_data_in_patch_request()
    {
        $data     = 'Hello World!';
        $bytes    = 12;
        $offset   = 0;
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('getData')
            ->once()
            ->with($offset, $bytes)
            ->andReturn($data);

        $this->tusClientMock
            ->shouldReceive('getUploadChecksumHeader')
            ->once()
            ->andReturn($checksum);

        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);

        $clientExceptionMock = m::mock(ClientException::class);

        $clientExceptionMock
            ->shouldReceive('getResponse')
            ->once()
            ->andReturn($responseMock);

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(416);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $guzzleMock
            ->shouldReceive('patch')
            ->once()
            ->with('/files/' . $checksum, [
                'body' => $data,
                'headers' => [
                    'Content-Type' => 'application/offset+octet-stream',
                    'Content-Length' => strlen($data),
                    'Upload-Checksum' => $checksum,
                    'Upload-Offset' => $offset,
                ],
            ])
            ->andThrow($clientExceptionMock);

        $this->tusClientMock->sendPatchRequest($bytes, $offset);
    }

    /**
     * @test
     *
     * @covers ::sendPatchRequest
     *
     * @expectedException \TusPhp\Exception\ConnectionException
     * @expectedExceptionMessage Connection aborted by user.
     */
    public function it_throws_connection_exception_if_user_aborts_connection_during_patch_request()
    {
        $data     = 'Hello World!';
        $bytes    = 12;
        $offset   = 0;
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('getData')
            ->once()
            ->with($offset, $bytes)
            ->andReturn($data);

        $this->tusClientMock
            ->shouldReceive('getUploadChecksumHeader')
            ->once()
            ->andReturn($checksum);

        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);

        $clientExceptionMock = m::mock(ClientException::class);

        $clientExceptionMock
            ->shouldReceive('getResponse')
            ->once()
            ->andReturn($responseMock);

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(100);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $guzzleMock
            ->shouldReceive('patch')
            ->once()
            ->with('/files/' . $checksum, [
                'body' => $data,
                'headers' => [
                    'Content-Type' => 'application/offset+octet-stream',
                    'Content-Length' => strlen($data),
                    'Upload-Checksum' => $checksum,
                    'Upload-Offset' => $offset,
                ],
            ])
            ->andThrow($clientExceptionMock);

        $this->tusClientMock->sendPatchRequest($bytes, $offset);
    }

    /**
     * @test
     *
     * @covers ::sendPatchRequest
     *
     * @expectedException \TusPhp\Exception\Exception
     * @expectedExceptionMessage Unable to open file.
     * @expectedExceptionCode    403
     */
    public function it_throws_exception_for_other_exceptions_in_patch_request()
    {
        $data     = 'Hello World!';
        $bytes    = 12;
        $offset   = 0;
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('getData')
            ->once()
            ->with($offset, $bytes)
            ->andReturn($data);

        $this->tusClientMock
            ->shouldReceive('getUploadChecksumHeader')
            ->once()
            ->andReturn($checksum);

        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);

        $clientExceptionMock = m::mock(ClientException::class);

        $clientExceptionMock
            ->shouldReceive('getResponse')
            ->twice()
            ->andReturn($responseMock);

        $responseMock
            ->shouldReceive('getBody')
            ->once()
            ->andReturn('Unable to open file.');

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(403);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $guzzleMock
            ->shouldReceive('patch')
            ->once()
            ->with('/files/' . $checksum, [
                'body' => $data,
                'headers' => [
                    'Content-Type' => 'application/offset+octet-stream',
                    'Content-Length' => strlen($data),
                    'Upload-Checksum' => $checksum,
                    'Upload-Offset' => $offset,
                ],
            ])
            ->andThrow($clientExceptionMock);

        $this->tusClientMock->sendPatchRequest($bytes, $offset);
    }

    /**
     * @test
     *
     * @covers ::sendPatchRequest
     *
     * @expectedException \TusPhp\Exception\ConnectionException
     * @expectedExceptionMessage Couldn't connect to server.
     */
    public function it_throws_connection_exception_if_it_cannot_connect_to_server()
    {
        $data     = 'Hello World!';
        $bytes    = 12;
        $offset   = 0;
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('getData')
            ->once()
            ->with($offset, $bytes)
            ->andReturn($data);

        $this->tusClientMock
            ->shouldReceive('getUploadChecksumHeader')
            ->once()
            ->andReturn($checksum);

        $guzzleMock = m::mock(Client::class);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $guzzleMock
            ->shouldReceive('patch')
            ->once()
            ->with('/files/' . $checksum, [
                'body' => $data,
                'headers' => [
                    'Content-Type' => 'application/offset+octet-stream',
                    'Content-Length' => strlen($data),
                    'Upload-Checksum' => $checksum,
                    'Upload-Offset' => $offset,
                ],
            ])
            ->andThrow(m::mock(ConnectException::class));


        $this->tusClientMock->sendPatchRequest($bytes, $offset);
    }

    /**
     * @test
     *
     * @covers ::sendPatchRequest
     */
    public function it_sends_a_patch_request()
    {
        $data     = 'Hello World!';
        $bytes    = 12;
        $offset   = 0;
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('getData')
            ->once()
            ->with($offset, $bytes)
            ->andReturn($data);

        $this->tusClientMock
            ->shouldReceive('getUploadChecksumHeader')
            ->once()
            ->andReturn($checksum);

        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $guzzleMock
            ->shouldReceive('patch')
            ->once()
            ->with('/files/' . $checksum, [
                'body' => $data,
                'headers' => [
                    'Content-Type' => 'application/offset+octet-stream',
                    'Content-Length' => strlen($data),
                    'Upload-Checksum' => $checksum,
                    'Upload-Offset' => $offset,
                ],
            ])
            ->andReturn($responseMock);

        $responseMock
            ->shouldReceive('getHeader')
            ->once()
            ->with('upload-offset')
            ->andReturn([$bytes]);

        $this->assertEquals($bytes, $this->tusClientMock->sendPatchRequest($bytes, $offset));
    }

    /**
     * @test
     *
     * @covers ::sendPatchRequest
     */
    public function it_sends_a_partial_patch_request()
    {
        $data     = 'Hello World!';
        $bytes    = 12;
        $offset   = 0;
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('getData')
            ->once()
            ->with($offset, $bytes)
            ->andReturn($data);

        $this->tusClientMock
            ->shouldReceive('getUploadChecksumHeader')
            ->once()
            ->andReturn($checksum);

        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $this->tusClientMock
            ->shouldReceive('isPartial')
            ->once()
            ->andReturn(true);

        $guzzleMock
            ->shouldReceive('patch')
            ->once()
            ->with('/files/' . $checksum, [
                'body' => $data,
                'headers' => [
                    'Content-Type' => 'application/offset+octet-stream',
                    'Content-Length' => mb_strlen($data),
                    'Upload-Checksum' => $checksum,
                    'Upload-Concat' => 'partial',
                ],
            ])
            ->andReturn($responseMock);

        $responseMock
            ->shouldReceive('getHeader')
            ->once()
            ->with('upload-offset')
            ->andReturn([$bytes]);

        $this->assertEquals($bytes, $this->tusClientMock->sendPatchRequest($bytes, $offset));
    }

    /**
     * @test
     *
     * @covers ::create
     */
    public function it_creates_a_resource_with_post_request()
    {
        $key          = uniqid();
        $checksum     = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $filePath     = __DIR__ . '/../Fixtures/empty.txt';
        $fileName     = 'file.txt';
        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(201);

        $this->tusClientMock
            ->shouldReceive('getChecksum')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $this->tusClientMock->file($filePath, $fileName);

        $guzzleMock
            ->shouldReceive('post')
            ->once()
            ->with('/files', [
                'headers' => [
                    'Upload-Length' => filesize($filePath),
                    'Upload-Key' => $key,
                    'Upload-Checksum' => 'sha256 ' . base64_encode($checksum),
                    'Upload-Metadata' => 'filename ' . base64_encode($fileName),
                ],
            ])
            ->andReturn($responseMock);

        $this->assertEmpty($this->tusClientMock->create($key));
    }

    /**
     * @test
     *
     * @covers ::create
     */
    public function it_creates_a_partial_resource_with_post_request()
    {
        $key          = uniqid();
        $checksum     = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $filePath     = __DIR__ . '/../Fixtures/empty.txt';
        $fileName     = 'file.txt';
        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(201);

        $this->tusClientMock
            ->shouldReceive('getChecksum')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $this->tusClientMock
            ->shouldReceive('isPartial')
            ->once()
            ->andReturn(true);

        $this->tusClientMock->file($filePath, $fileName);

        $guzzleMock
            ->shouldReceive('post')
            ->once()
            ->with('/files', [
                'headers' => [
                    'Upload-Length' => filesize($filePath),
                    'Upload-Key' => $key,
                    'Upload-Checksum' => 'sha256 ' . base64_encode($checksum),
                    'Upload-Metadata' => 'filename ' . base64_encode($fileName),
                    'Upload-Concat' => 'partial',
                ],
            ])
            ->andReturn($responseMock);

        $this->assertEmpty($this->tusClientMock->create($key));
    }

    /**
     * @test
     *
     * @covers ::create
     *
     * @expectedException \TusPhp\Exception\FileException
     * @expectedExceptionMessage Unable to create resource.
     */
    public function it_throws_exception_when_unable_to_create_resource()
    {
        $key          = uniqid();
        $filePath     = __DIR__ . '/../Fixtures/empty.txt';
        $fileName     = 'file.txt';
        $checksum     = hash_file('sha256', $filePath);
        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(400);

        $this->tusClientMock
            ->shouldReceive('getChecksum')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $this->tusClientMock->file($filePath, $fileName);

        $guzzleMock
            ->shouldReceive('post')
            ->once()
            ->with('/files', [
                'headers' => [
                    'Upload-Length' => filesize($filePath),
                    'Upload-Key' => $key,
                    'Upload-Checksum' => 'sha256 ' . base64_encode($checksum),
                    'Upload-Metadata' => 'filename ' . base64_encode($fileName),
                ],
            ])
            ->andReturn($responseMock);

        $this->tusClientMock->create($key);
    }

    /**
     * @test
     *
     * @covers ::concat
     */
    public function it_creates_a_concat_resource_with_post_request()
    {
        $key          = uniqid();
        $checksum     = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $filePath     = __DIR__ . '/../Fixtures/empty.txt';
        $fileName     = 'file.txt';
        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);
        $partials     = ['/files/a', '/files/b', 'files/c'];

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(201);

        $responseMock
            ->shouldReceive('getBody')
            ->once()
            ->andReturn(json_encode([
                'data' => [
                    'checksum' => $checksum,
                ],
            ]));

        $this->tusClientMock
            ->shouldReceive('getChecksum')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $this->tusClientMock->file($filePath, $fileName);

        $guzzleMock
            ->shouldReceive('post')
            ->once()
            ->with('/files', [
                'headers' => [
                    'Upload-Length' => filesize($filePath),
                    'Upload-Key' => $key,
                    'Upload-Checksum' => 'sha256 ' . base64_encode($checksum),
                    'Upload-Metadata' => 'filename ' . base64_encode($fileName),
                    'Upload-Concat' => 'final;' . implode(' ', $partials),
                ],
            ])
            ->andReturn($responseMock);

        $this->assertEquals(
            $checksum,
            $this->tusClientMock->concat($key, $partials[0], $partials[1], $partials[2])
        );
    }

    /**
     * @test
     *
     * @covers ::concat
     *
     * @expectedException \TusPhp\Exception\FileException
     * @expectedExceptionMessage Unable to create resource.
     */
    public function it_throws_exception_when_unable_to_create_concat_resource()
    {
        $key          = uniqid();
        $filePath     = __DIR__ . '/../Fixtures/empty.txt';
        $fileName     = 'file.txt';
        $checksum     = hash_file('sha256', $filePath);
        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);
        $partials     = ['/files/a', '/files/b', 'files/c'];

        $responseMock
            ->shouldReceive('getBody')
            ->once()
            ->andReturn(null);

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(400);

        $this->tusClientMock
            ->shouldReceive('getChecksum')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $this->tusClientMock->file($filePath, $fileName);

        $guzzleMock
            ->shouldReceive('post')
            ->once()
            ->with('/files', [
                'headers' => [
                    'Upload-Length' => filesize($filePath),
                    'Upload-Key' => $key,
                    'Upload-Checksum' => 'sha256 ' . base64_encode($checksum),
                    'Upload-Metadata' => 'filename ' . base64_encode($fileName),
                    'Upload-Concat' => 'final;' . implode(' ', $partials),
                ],
            ])
            ->andReturn($responseMock);

        $this->tusClientMock->concat($key, $partials[0], $partials[1], $partials[2]);
    }

    /**
     * @test
     *
     * @covers ::concat
     *
     * @expectedException \TusPhp\Exception\FileException
     * @expectedExceptionMessage Unable to create resource.
     */
    public function it_throws_exception_when_unable_to_get_checksum_in_concat()
    {
        $filePath     = __DIR__ . '/../Fixtures/empty.txt';
        $fileName     = 'file.txt';
        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);
        $partials     = ['/files/a', '/files/b', 'files/c'];

        $responseMock
            ->shouldReceive('getBody')
            ->once()
            ->andReturn(null);

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(201);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $this->tusClientMock
            ->shouldReceive('getChecksum')
            ->once()
            ->andReturn('');

        $this->tusClientMock->file($filePath, $fileName);

        $guzzleMock
            ->shouldReceive('post')
            ->once()
            ->with('/files', [
                'headers' => [
                    'Upload-Length' => filesize($filePath),
                    'Upload-Key' => '',
                    'Upload-Checksum' => 'sha256 ',
                    'Upload-Metadata' => 'filename ' . base64_encode($fileName),
                    'Upload-Concat' => 'final;' . implode(' ', $partials),
                ],
            ])
            ->andReturn($responseMock);

        $this->tusClientMock->concat('', $partials[0], $partials[1], $partials[2]);
    }

    /**
     * @test
     *
     * @covers ::delete
     */
    public function it_sends_a_delete_request()
    {
        $uploadKey    = '74f02d6da32';
        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);

        $guzzleMock
            ->shouldReceive('delete')
            ->once()
            ->with('/files/' . $uploadKey)
            ->andReturn($responseMock);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $response = $this->tusClientMock->delete($uploadKey);

        $this->assertNull($response);
    }

    /**
     * @test
     *
     * @covers ::delete
     *
     * @expectedException \TusPhp\Exception\FileException
     * @expectedExceptionMessage File not found.
     */
    public function it_throws_404_for_invalid_delete_request()
    {
        $uploadKey    = '74f02d6da32';
        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);

        $clientExceptionMock = m::mock(ClientException::class);

        $clientExceptionMock
            ->shouldReceive('getResponse')
            ->once()
            ->andReturn($responseMock);

        $guzzleMock
            ->shouldReceive('delete')
            ->once()
            ->with('/files/' . $uploadKey)
            ->andThrow($clientExceptionMock);

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(404);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $response = $this->tusClientMock->delete($uploadKey);

        $this->assertNull($response);
    }

    /**
     * @test
     *
     * @covers ::delete
     *
     * @expectedException \TusPhp\Exception\FileException
     * @expectedExceptionMessage File not found.
     */
    public function it_throws_404_for_response_http_gone()
    {
        $uploadKey    = '74f02d6da32';
        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);

        $clientExceptionMock = m::mock(ClientException::class);

        $clientExceptionMock
            ->shouldReceive('getResponse')
            ->once()
            ->andReturn($responseMock);

        $guzzleMock
            ->shouldReceive('delete')
            ->once()
            ->with('/files/' . $uploadKey)
            ->andThrow($clientExceptionMock);

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(410);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $response = $this->tusClientMock->delete($uploadKey);

        $this->assertNull($response);
    }

    /**
     * @test
     *
     * @covers ::getData
     */
    public function it_gets_all_data()
    {
        $offset     = 0;
        $filePath   = __DIR__ . '/../Fixtures/data.txt';
        $dataLength = 92;

        $this->tusClientMock
            ->shouldReceive('getFilePath')
            ->once()
            ->andReturn($filePath);

        $data = $this->tusClientMock->getData($offset, $dataLength);

        $this->assertEquals($dataLength, strlen($data));
        $this->assertEquals(
            'The Project Gutenberg EBook of The Adventures of Sherlock Holmes by Sir Arthur Conan Doyle.',
            trim($data)
        );
    }

    /**
     * @test
     *
     * @covers ::getData
     */
    public function it_gets_partial_data()
    {
        $offset     = 49;
        $filePath   = __DIR__ . '/../Fixtures/data.txt';
        $dataLength = 15;

        $this->tusClientMock
            ->shouldReceive('getFilePath')
            ->once()
            ->andReturn($filePath);

        $data = $this->tusClientMock->getData($offset, $dataLength);

        $this->assertEquals($dataLength, strlen($data));
        $this->assertEquals('Sherlock Holmes', $data);
    }

    /**
     * @test
     *
     * @covers ::getUploadChecksumHeader
     */
    public function it_gets_upload_checksum_header()
    {
        $file     = __DIR__ . '/../Fixtures/empty.txt';
        $checksum = hash_file('crc32', $file);

        $this->tusClientMock
            ->shouldReceive('getChecksum')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('getChecksumAlgorithm')
            ->once()
            ->andReturn('crc32');

        $this->assertEquals('crc32 ' . base64_encode($checksum), $this->tusClientMock->getUploadChecksumHeader());
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

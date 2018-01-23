<?php

namespace TusPhp\Test;

use Mockery as m;
use GuzzleHttp\Client;
use phpmock\MockBuilder;
use TusPhp\Cache\FileStore;
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
     * @covers ::getApiPath
     * @covers ::setApiPath
     */
    public function it_sets_and_gets_api_path()
    {
        $this->assertEquals('/files', $this->tusClient->getApiPath());

        $this->tusClient->setApiPath('/api');

        $this->assertEquals('/api', $this->tusClient->getApiPath());
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
     * @covers ::getChecksumAlgorithm
     * @covers ::setChecksumAlgorithm
     */
    public function it_sets_and_gets_checksum_algorithm()
    {
        $this->assertEquals('sha256', $this->tusClient->getChecksumAlgorithm());

        $this->tusClient->setChecksumAlgorithm('crc32');

        $this->assertEquals('crc32', $this->tusClient->getChecksumAlgorithm());
    }

    /**
     * @test
     *
     * @covers ::upload
     */
    public function it_uploads_a_file()
    {
        $bytes    = 100;
        $checksum = hash_file('sha256', __DIR__ . '/../Fixtures/data.txt');

        $this->tusClientMock
            ->shouldReceive('getChecksum')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->with($checksum)
            ->andReturn(0);

        $this->tusClientMock
            ->shouldReceive('sendPatchRequest')
            ->once()
            ->with($checksum, $bytes)
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
        $bytes    = 100;
        $checksum = hash_file('sha256', __DIR__ . '/../Fixtures/data.txt');

        $this->tusClientMock
            ->shouldReceive('getChecksum')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('getFileSize')
            ->once()
            ->andReturn($bytes);

        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->with($checksum)
            ->andReturn(0);

        $this->tusClientMock
            ->shouldReceive('sendPatchRequest')
            ->once()
            ->with($checksum, $bytes)
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
        $bytes    = 100;
        $checksum = hash_file('sha256', __DIR__ . '/../Fixtures/data.txt');

        $this->tusClientMock
            ->shouldReceive('getChecksum')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->with($checksum)
            ->andThrow(new FileException);

        $this->tusClientMock
            ->shouldReceive('create')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('sendPatchRequest')
            ->once()
            ->with($checksum, $bytes)
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
        $bytes    = 100;
        $checksum = hash_file('sha256', __DIR__ . '/../Fixtures/data.txt');

        $this->tusClientMock
            ->shouldReceive('getChecksum')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->with($checksum)
            ->andThrow(m::mock(ClientException::class));

        $this->tusClientMock
            ->shouldReceive('create')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('sendPatchRequest')
            ->once()
            ->with($checksum, $bytes)
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
        $file     = __DIR__ . '/../Fixtures/data.txt';
        $checksum = hash_file('sha256', $file);

        $this->tusClientMock
            ->shouldReceive('getFilePath')
            ->once()
            ->andReturn($file);

        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->with($checksum)
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
        $checksum = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

        $this->tusClientMock
            ->shouldReceive('getChecksum')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->with($checksum)
            ->andThrow(new FileException);

        $this->assertFalse($this->tusClientMock->getOffset($checksum));
    }

    /**
     * @test
     *
     * @covers ::getOffset
     */
    public function it_returns_false_for_client_exception_in_get_offset()
    {
        $checksum = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

        $this->tusClientMock
            ->shouldReceive('getChecksum')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->with($checksum)
            ->andThrow(m::mock(ClientException::class));

        $this->assertFalse($this->tusClientMock->getOffset($checksum));
    }

    /**
     * @test
     *
     * @covers ::getOffset
     */
    public function it_gets_offset_for_partially_uploaded_resource()
    {
        $checksum = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

        $this->tusClientMock
            ->shouldReceive('getChecksum')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->with($checksum)
            ->andReturn(100);

        $this->assertEquals(100, $this->tusClientMock->getOffset($checksum));
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
        $bytes    = 12;
        $data     = 'Hello World!';
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusClientMock
            ->shouldReceive('getData')
            ->once()
            ->with($checksum, $bytes)
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
                ],
            ])
            ->andThrow($clientExceptionMock);

        $this->tusClientMock->sendPatchRequest($checksum, $bytes);
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
        $bytes    = 12;
        $data     = 'Hello World!';
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusClientMock
            ->shouldReceive('getData')
            ->once()
            ->with($checksum, $bytes)
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
                ],
            ])
            ->andThrow($clientExceptionMock);


        $this->tusClientMock->sendPatchRequest($checksum, $bytes);
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
        $bytes    = 12;
        $data     = 'Hello World!';
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusClientMock
            ->shouldReceive('getData')
            ->once()
            ->with($checksum, $bytes)
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
                ],
            ])
            ->andThrow($clientExceptionMock);

        $this->tusClientMock->sendPatchRequest($checksum, $bytes);
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
        $bytes    = 12;
        $data     = 'Hello World!';
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusClientMock
            ->shouldReceive('getData')
            ->once()
            ->with($checksum, $bytes)
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
                ],
            ])
            ->andThrow(m::mock(ConnectException::class));


        $this->tusClientMock->sendPatchRequest($checksum, $bytes);
    }

    /**
     * @test
     *
     * @covers ::sendPatchRequest
     */
    public function it_sends_a_patch_request()
    {
        $bytes    = 12;
        $data     = 'Hello World!';
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusClientMock
            ->shouldReceive('getData')
            ->once()
            ->with($checksum, $bytes)
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
                ],
            ])
            ->andReturn($responseMock);

        $responseMock
            ->shouldReceive('getHeader')
            ->once()
            ->with('upload-offset')
            ->andReturn([$bytes]);

        $this->assertEquals($bytes, $this->tusClientMock->sendPatchRequest($checksum, $bytes));
    }

    /**
     * @test
     *
     * @covers ::create
     */
    public function it_creates_a_resource_with_post_request()
    {
        $checksum     = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $filePath     = __DIR__ . '/../Fixtures/empty.txt';
        $fileName     = 'file.txt';
        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);

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
                    'Upload-Checksum' => 'sha256 ' . base64_encode(hash_file('sha256', $filePath)),
                    'Upload-Metadata' => 'filename ' . base64_encode($fileName),
                ],
            ])
            ->andReturn($responseMock);

        $this->assertEquals($checksum, $this->tusClientMock->create());
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
        $filePath     = __DIR__ . '/../Fixtures/empty.txt';
        $fileName     = 'file.txt';
        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);

        $responseMock
            ->shouldReceive('getBody')
            ->once()
            ->andReturn(null);

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(400);

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
                    'Upload-Checksum' => 'sha256 ' . base64_encode(hash_file('sha256', $filePath)),
                    'Upload-Metadata' => 'filename ' . base64_encode($fileName),
                ],
            ])
            ->andReturn($responseMock);

        $this->tusClientMock->create();
    }

    /**
     * @test
     *
     * @covers ::create
     *
     * @expectedException \TusPhp\Exception\FileException
     * @expectedExceptionMessage Unable to create resource.
     */
    public function it_throws_exception_when_unable_to_get_checksum()
    {
        $filePath     = __DIR__ . '/../Fixtures/empty.txt';
        $fileName     = 'file.txt';
        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);

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
                    'Upload-Checksum' => 'sha256 ',
                    'Upload-Metadata' => 'filename ' . base64_encode($fileName),
                ],
            ])
            ->andReturn($responseMock);

        $this->tusClientMock->create();
    }

    /**
     * @test
     *
     * @covers ::delete
     */
    public function it_sends_a_delete_request()
    {
        $checksum     = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);

        $guzzleMock
            ->shouldReceive('delete')
            ->once()
            ->with('/files/' . $checksum, [
                'headers' => [
                    'Tus-Resumable' => '1.0.0',
                ],
            ])
            ->andReturn($responseMock);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $response = $this->tusClientMock->delete($checksum);

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
        $checksum     = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
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
            ->with('/files/' . $checksum, [
                'headers' => [
                    'Tus-Resumable' => '1.0.0',
                ],
            ])
            ->andThrow($clientExceptionMock);

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(404);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $response = $this->tusClientMock->delete($checksum);

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
        $checksum     = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
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
            ->with('/files/' . $checksum, [
                'headers' => [
                    'Tus-Resumable' => '1.0.0',
                ],
            ])
            ->andThrow($clientExceptionMock);

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(410);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $response = $this->tusClientMock->delete($checksum);

        $this->assertNull($response);
    }

    /**
     * @test
     *
     * @covers ::getData
     */
    public function it_gets_all_data()
    {
        $checksum   = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $filePath   = __DIR__ . '/../Fixtures/data.txt';
        $dataLength = 92;

        $this->tusClientMock
            ->shouldReceive('getFilePath')
            ->once()
            ->andReturn($filePath);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with($checksum)
            ->andReturn([
                'offset' => 0,
            ]);

        $this->tusClientMock
            ->shouldReceive('getCache')
            ->once()
            ->andReturn($cacheMock);

        $data = $this->tusClientMock->getData($checksum, $dataLength);

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
        $checksum   = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $filePath   = __DIR__ . '/../Fixtures/data.txt';
        $dataLength = 15;

        $this->tusClientMock
            ->shouldReceive('getFilePath')
            ->once()
            ->andReturn($filePath);

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with($checksum)
            ->andReturn([
                'offset' => 49,
            ]);

        $this->tusClientMock
            ->shouldReceive('getCache')
            ->once()
            ->andReturn($cacheMock);

        $data = $this->tusClientMock->getData($checksum, $dataLength);

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

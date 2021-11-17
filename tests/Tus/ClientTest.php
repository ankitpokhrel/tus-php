<?php

namespace TusPhp\Test\Tus;

use Mockery as m;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Utils;
use TusPhp\Cache\FileStore;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use TusPhp\Exception\TusException;
use TusPhp\Tus\Client as TusClient;
use TusPhp\Exception\FileException;
use GuzzleHttp\Exception\ClientException;
use TusPhp\Exception\ConnectionException;
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
    public function setUp(): void
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
     */
    public function it_throws_exception_for_invalid_file(): void
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessageMatches('/Cannot read file: [a-zA-Z0-9-\/.]+/');

        $this->tusClient->file('/path/to/invalid/file.txt');
    }

    /**
     * @test
     *
     * @covers ::file
     * @covers ::getFilePath
     * @covers ::getFileName
     * @covers ::getFileSize
     */
    public function it_sets_and_gets_file_attributes(): void
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
    public function it_sets_and_gets_filename(): void
    {
        $this->assertNull($this->tusClient->getFileName());
        $this->assertInstanceOf(TusClient::class, $this->tusClient->setFileName('file.txt'));
        $this->assertEquals('file.txt', $this->tusClient->getFileName());
    }

    /**
     * @test
     *
     * @covers ::addMetadata
     * @covers ::removeMetadata
     * @covers ::setMetadata
     * @covers ::getMetadata
     */
    public function it_sets_and_gets_metadata(): void
    {
        $filePath = __DIR__ . '/../Fixtures/empty.txt';

        $this->tusClient->file($filePath);
        $this->assertEquals(['filename' => base64_encode('empty.txt')], $this->tusClient->getMetadata());

        $this->tusClient->addMetadata('filename', 'file.mp4');
        $this->assertEquals(['filename' => base64_encode('file.mp4')], $this->tusClient->getMetadata());

        $this->tusClient->addMetadata('filetype', 'video/mp4');
        $this->assertEquals([
            'filename' => base64_encode('file.mp4'),
            'filetype' => base64_encode('video/mp4'),
        ], $this->tusClient->getMetadata());

        $this->tusClient->setMetadata([
            'filename' => 'file.mp4',
            'filetype' => 'video/mp4',
        ]);

        $this->assertEquals([
            'filename' => base64_encode('file.mp4'),
            'filetype' => base64_encode('video/mp4'),
        ], $this->tusClient->getMetadata());

        $this->tusClient->removeMetadata('filetype');
        $this->assertEquals(['filename' => base64_encode('file.mp4')], $this->tusClient->getMetadata());
    }

    /**
     * @test
     *
     * @covers ::__construct
     * @covers ::getClient
     */
    public function it_gets_client(): void
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
    public function it_injects_options_in_client(): void
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
     * @covers ::setChecksum
     */
    public function it_sets_and_gets_checksum(): void
    {
        $file     = __DIR__ . '/../Fixtures/data.txt';
        $checksum = hash_file('sha256', $file);

        $this->tusClientMock
            ->shouldReceive('getFilePath')
            ->once()
            ->andReturn($file);

        $this->assertEquals($checksum, $this->tusClientMock->getChecksum());

        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $this->assertInstanceOf(TusClient::class, $this->tusClientMock->setChecksum($checksum));
    }

    /**
     * @test
     *
     * @covers ::setKey
     * @covers ::getKey
     */
    public function it_sets_and_gets_key(): void
    {
        $key = uniqid();

        $this->assertInstanceOf(TusClient::class, $this->tusClient->setKey($key));
        $this->assertEquals($key, $this->tusClient->getKey());
    }

    /**
     * @test
     *
     * @covers ::getUrl
     */
    public function it_gets_url_from_cache(): void
    {
        $key = uniqid();
        $url = 'https://server.tus.local';

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with($key)
            ->andReturn([
                'location' => $url,
            ]);

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($key);

        $this->tusClientMock
            ->shouldReceive('getCache')
            ->once()
            ->andReturn($cacheMock);

        $this->assertEquals($url, $this->tusClientMock->getUrl());
    }

    /**
     * @test
     *
     * @covers ::getUrl
     */
    public function it_throws_file_exception_for_empty_url(): void
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('File not found.');

        $key = uniqid();

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with($key)
            ->andReturnNull();

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($key);

        $this->tusClientMock
            ->shouldReceive('getCache')
            ->once()
            ->andReturn($cacheMock);

        $this->tusClientMock->getUrl();
    }

    /**
     * @test
     *
     * @covers ::setChecksumAlgorithm
     * @covers ::getChecksumAlgorithm
     */
    public function it_sets_and_gets_checksum_algorithm(): void
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
    public function it_sets_and_gets_partial(): void
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
    public function it_set_state_only_for_partial_equals_false(): void
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
    public function it_generates_unique_key_for_partial_checksum(): void
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
     * @covers ::isExpired
     */
    public function it_returns_false_if_upload_is_not_expired(): void
    {
        $key = uniqid();
        $url = 'https://server.tus.local';

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with($key)
            ->andReturn([
                'location' => $url,
                'expires_at' => 'Sat, 09 Dec 2017 00:00:00 GMT',
            ]);

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($key);

        $this->tusClientMock
            ->shouldReceive('getCache')
            ->once()
            ->andReturn($cacheMock);

        $this->assertFalse($this->tusClientMock->isExpired());
    }

    /**
     * @test
     *
     * @covers ::isExpired
     */
    public function it_returns_true_if_upload_is_expired(): void
    {
        $key = uniqid();
        $url = 'https://server.tus.local';

        $cacheMock = m::mock(FileStore::class);
        $cacheMock
            ->shouldReceive('get')
            ->once()
            ->with($key)
            ->andReturn([
                'location' => $url,
                'expires_at' => 'Thu, 7 Dec 2017 00:00:00 GMT',
            ]);

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($key);

        $this->tusClientMock
            ->shouldReceive('getCache')
            ->once()
            ->andReturn($cacheMock);

        $this->assertTrue($this->tusClientMock->isExpired());
    }

    /**
     * @test
     *
     * @covers ::upload
     */
    public function it_uploads_a_file(): void
    {
        $bytes  = 100;
        $offset = 0;

        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->andReturn(0);

        $this->tusClientMock
            ->shouldReceive('isExpired')
            ->once()
            ->andReturn(false);

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
    public function it_uploads_full_file_if_size_is_not_given(): void
    {
        $bytes  = 100;
        $offset = 0;

        $this->tusClientMock
            ->shouldReceive('getFileSize')
            ->once()
            ->andReturn($bytes);

        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->andReturn(0);

        $this->tusClientMock
            ->shouldReceive('isExpired')
            ->once()
            ->andReturn(false);

        $this->tusClientMock
            ->shouldReceive('sendPatchRequest')
            ->once()
            ->with($bytes, $offset)
            ->andReturn($bytes);

        $this->assertEquals($bytes, $this->tusClientMock->upload());
    }

    /**
     * @test
     */
    public function it_should_not_resume_upload_if_upload_is_expired(): void
    {
        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Upload expired.');

        $this->tusClientMock
            ->shouldReceive('getFileSize')
            ->once()
            ->andReturn(100);

        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->andReturn(0);

        $this->tusClientMock
            ->shouldReceive('isExpired')
            ->once()
            ->andReturn(true);

        $this->tusClientMock->upload();
    }

    /**
     * @test
     *
     * @covers ::upload
     */
    public function it_creates_and_then_uploads_a_file_in_file_exception(): void
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
            ->andThrow(new FileException());

        $this->tusClientMock
            ->shouldReceive('create')
            ->once()
            ->andReturn($key);

        $this->tusClientMock
            ->shouldReceive('isExpired')
            ->once()
            ->andReturn(false);

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
    public function it_creates_and_then_uploads_a_file_in_client_exception(): void
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
            ->andThrow(m::mock(ClientException::class));

        $this->tusClientMock
            ->shouldReceive('create')
            ->once()
            ->andReturn($key);

        $this->tusClientMock
            ->shouldReceive('isExpired')
            ->once()
            ->andReturn(false);

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
    public function it_throws_connection_exception_for_network_issues(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Couldn\'t connect to server.');

        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->andThrow(m::mock(ConnectException::class));

        $this->tusClientMock->upload(100);
    }

    /**
     * @test
     *
     * @covers ::getOffset
     */
    public function it_returns_false_for_file_exception_in_get_offset(): void
    {
        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->andThrow(new FileException());

        $this->assertFalse($this->tusClientMock->getOffset());
    }

    /**
     * @test
     *
     * @covers ::getOffset
     */
    public function it_returns_false_for_client_exception_in_get_offset(): void
    {
        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->andThrow(m::mock(ClientException::class));

        $this->assertFalse($this->tusClientMock->getOffset());
    }

    /**
     * @test
     *
     * @covers ::getOffset
     */
    public function it_gets_offset_for_partially_uploaded_resource(): void
    {
        $this->tusClientMock
            ->shouldReceive('sendHeadRequest')
            ->once()
            ->andReturn(100);

        $this->assertEquals(100, $this->tusClientMock->getOffset());
    }

    /**
     * @test
     *
     * @covers ::sendHeadRequest
     */
    public function it_sends_a_head_request(): void
    {
        $key          = '74f02d6da32';
        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);

        $guzzleMock
            ->shouldReceive('head')
            ->once()
            ->with('/files/' . $key)
            ->andReturn($responseMock);

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(200);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $this->tusClientMock
            ->shouldReceive('getUrl')
            ->once()
            ->andReturn('/files/' . $key);

        $responseMock
            ->shouldReceive('getHeader')
            ->once()
            ->with('upload-offset')
            ->andReturn([100]);

        $this->assertEquals(100, $this->tusClientMock->sendHeadRequest());
    }

    /**
     * @test
     *
     * @covers ::sendHeadRequest
     */
    public function it_throws_file_exception_in_head_request(): void
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('File not found.');

        $key          = '74f02d6da32';
        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);

        $guzzleMock
            ->shouldReceive('head')
            ->once()
            ->with('/files/' . $key)
            ->andReturn($responseMock);

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(404);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $this->tusClientMock
            ->shouldReceive('getUrl')
            ->once()
            ->andReturn('/files/' . $key);

        $this->tusClientMock->sendHeadRequest();
    }

    /**
     * @test
     *
     * @covers ::sendPatchRequest
     * @covers ::handleClientException
     */
    public function it_throws_file_exception_for_corrupt_data_in_patch_request(): void
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('The uploaded file is corrupt.');

        $data     = 'Hello World!';
        $bytes    = 12;
        $offset   = 0;
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

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

        $this->tusClientMock
            ->shouldReceive('getUrl')
            ->once()
            ->andReturn('http://tus-server/files/' . $checksum);

        $guzzleMock
            ->shouldReceive('patch')
            ->once()
            ->with('http://tus-server/files/' . $checksum, [
                'body' => $data,
                'headers' => [
                    'Content-Type' => 'application/offset+octet-stream',
                    'Content-Length' => \strlen($data),
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
     * @covers ::handleClientException
     */
    public function it_throws_connection_exception_if_user_aborts_connection_during_patch_request(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Connection aborted by user.');

        $data     = 'Hello World!';
        $bytes    = 12;
        $offset   = 0;
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusClientMock
            ->shouldReceive('getData')
            ->once()
            ->with($offset, $bytes)
            ->andReturn($data);

        $this->tusClientMock
            ->shouldReceive('getUploadChecksumHeader')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('getUrl')
            ->once()
            ->andReturn('http://tus-server/files/' . $checksum);

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
            ->with('http://tus-server/files/' . $checksum, [
                'body' => $data,
                'headers' => [
                    'Content-Type' => 'application/offset+octet-stream',
                    'Content-Length' => \strlen($data),
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
     * @covers ::handleClientException
     */
    public function it_throws_tus_exception_for_unsupported_media_types(): void
    {
        $this->expectException(TusException::class);
        $this->expectExceptionMessage('Unsupported media types.');

        $data     = 'Hello World!';
        $bytes    = 12;
        $offset   = 0;
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusClientMock
            ->shouldReceive('getData')
            ->once()
            ->with($offset, $bytes)
            ->andReturn($data);

        $this->tusClientMock
            ->shouldReceive('getUploadChecksumHeader')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('getUrl')
            ->once()
            ->andReturn('http://tus-server/files/' . $checksum);

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
            ->andReturn(415);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $guzzleMock
            ->shouldReceive('patch')
            ->once()
            ->with('http://tus-server/files/' . $checksum, [
                'body' => $data,
                'headers' => [
                    'Content-Type' => 'application/offset+octet-stream',
                    'Content-Length' => \strlen($data),
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
     * @covers ::handleClientException
     */
    public function it_throws_exception_for_other_exceptions_in_patch_request(): void
    {
        $this->expectException(TusException::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage('Unable to open file.');

        $data     = 'Hello World!';
        $bytes    = 12;
        $offset   = 0;
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusClientMock
            ->shouldReceive('getData')
            ->once()
            ->with($offset, $bytes)
            ->andReturn($data);

        $this->tusClientMock
            ->shouldReceive('getUploadChecksumHeader')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('getUrl')
            ->once()
            ->andReturn('http://tus-server/files/' . $checksum);

        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);

        $clientExceptionMock = m::mock(ClientException::class);

        $clientExceptionMock
            ->shouldReceive('getResponse')
            ->once()
            ->andReturn($responseMock);

        $msg  = 'Unable to open file.';
        $body = class_exists(Utils::class) ? Utils::streamFor($msg) : $msg;
        $responseMock
            ->shouldReceive('getBody')
            ->once()
            ->andReturn($body);

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
            ->with('http://tus-server/files/' . $checksum, [
                'body' => $data,
                'headers' => [
                    'Content-Type' => 'application/offset+octet-stream',
                    'Content-Length' => \strlen($data),
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
     * @covers ::handleClientException
     */
    public function it_throws_connection_exception_if_it_cannot_connect_to_server(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Couldn\'t connect to server.');

        $data     = 'Hello World!';
        $bytes    = 12;
        $offset   = 0;
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusClientMock
            ->shouldReceive('getData')
            ->once()
            ->with($offset, $bytes)
            ->andReturn($data);

        $this->tusClientMock
            ->shouldReceive('getUploadChecksumHeader')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('getUrl')
            ->once()
            ->andReturn('http://tus-server/files/' . $checksum);

        $guzzleMock = m::mock(Client::class);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $guzzleMock
            ->shouldReceive('patch')
            ->once()
            ->with('http://tus-server/files/' . $checksum, [
                'body' => $data,
                'headers' => [
                    'Content-Type' => 'application/offset+octet-stream',
                    'Content-Length' => \strlen($data),
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
    public function it_sends_a_patch_request(): void
    {
        $data     = 'Hello World!';
        $bytes    = 12;
        $offset   = 0;
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusClientMock
            ->shouldReceive('getData')
            ->once()
            ->with($offset, $bytes)
            ->andReturn($data);

        $this->tusClientMock
            ->shouldReceive('getUploadChecksumHeader')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('getUrl')
            ->once()
            ->andReturn('http://tus-server/files/' . $checksum);

        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $guzzleMock
            ->shouldReceive('patch')
            ->once()
            ->with('http://tus-server/files/' . $checksum, [
                'body' => $data,
                'headers' => [
                    'Content-Type' => 'application/offset+octet-stream',
                    'Content-Length' => \strlen($data),
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
    public function it_sends_a_partial_patch_request(): void
    {
        $data     = 'Hello World!';
        $bytes    = 12;
        $offset   = 0;
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->tusClientMock
            ->shouldReceive('getData')
            ->once()
            ->with($offset, $bytes)
            ->andReturn($data);

        $this->tusClientMock
            ->shouldReceive('getUploadChecksumHeader')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('getUrl')
            ->once()
            ->andReturn('http://tus-server/files/' . $checksum);

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
            ->with('http://tus-server/files/' . $checksum, [
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
     * @covers ::createWithUpload
     */
    public function it_creates_a_resource_with_post_request(): void
    {
        $key          = uniqid();
        $checksum     = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $filePath     = __DIR__ . '/../Fixtures/empty.txt';
        $fileName     = 'file.txt';
        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);
        $cacheMock    = m::mock(FileStore::class);

        $cacheMock
            ->shouldReceive('set')
            ->once()
            ->with($key, [
                'location' => 'http://tus-server/files/' . $key,
                'expires_at' => 'Sat, 09 Dec 2017 00:00:00 GMT',
            ])
            ->andReturnNull();

        $cacheMock
            ->shouldReceive('getTtl')
            ->once()
            ->andReturn(86400);

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(201);

        $responseMock
            ->shouldReceive('getHeader')
            ->once()
            ->with('location')
            ->andReturn(['http://tus-server/files/' . $key]);

        $this->tusClientMock
            ->shouldReceive('getChecksum')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $this->tusClientMock
            ->shouldReceive('getCache')
            ->times(3)
            ->andReturn($cacheMock);

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($key);

        $this->tusClientMock->file($filePath, $fileName);

        $guzzleMock
            ->shouldReceive('post')
            ->once()
            ->with('/files', [
                'body' => '',
                'headers' => [
                    'Upload-Length' => filesize($filePath),
                    'Upload-Key' => $key,
                    'Upload-Checksum' => 'sha256 ' . base64_encode($checksum),
                    'Upload-Metadata' => 'filename ' . base64_encode($fileName),
                ],
            ])
            ->andReturn($responseMock);

        $this->assertEquals('http://tus-server/files/' . $key, $this->tusClientMock->create($key));
    }

    /**
     * @test
     *
     * @covers ::create
     * @covers ::createWithUpload
     */
    public function it_creates_a_partial_resource_with_post_request(): void
    {
        $key          = uniqid();
        $checksum     = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $filePath     = __DIR__ . '/../Fixtures/empty.txt';
        $fileName     = 'file.txt';
        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);
        $cacheMock    = m::mock(FileStore::class);

        $cacheMock
            ->shouldReceive('set')
            ->once()
            ->with($key, [
                'location' => 'http://tus-server/files/' . $key,
                'expires_at' => 'Sat, 09 Dec 2017 00:00:00 GMT',
            ])
            ->andReturnNull();

        $cacheMock
            ->shouldReceive('getTtl')
            ->once()
            ->andReturn(86400);

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(201);

        $responseMock
            ->shouldReceive('getHeader')
            ->once()
            ->with('location')
            ->andReturn(['http://tus-server/files/' . $key]);

        $this->tusClientMock
            ->shouldReceive('getChecksum')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $this->tusClientMock
            ->shouldReceive('getCache')
            ->times(3)
            ->andReturn($cacheMock);

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($key);

        $this->tusClientMock
            ->shouldReceive('isPartial')
            ->once()
            ->andReturn(true);

        $this->tusClientMock->file($filePath, $fileName);

        $guzzleMock
            ->shouldReceive('post')
            ->once()
            ->with('/files', [
                'body' => '',
                'headers' => [
                    'Upload-Length' => filesize($filePath),
                    'Upload-Key' => $key,
                    'Upload-Checksum' => 'sha256 ' . base64_encode($checksum),
                    'Upload-Metadata' => 'filename ' . base64_encode($fileName),
                    'Upload-Concat' => 'partial',
                ],
            ])
            ->andReturn($responseMock);

        $this->assertEquals('http://tus-server/files/' . $key, $this->tusClientMock->create($key));
    }

    public function createWithUploadDataProvider()
    {
        return [
            [-1],
            [10],
        ];
    }

    /**
     * @test
     *
     * @covers ::createWithUpload
     * @dataProvider createWithUploadDataProvider
     *
     * @param int $bytes
     */
    public function it_uploads_a_resource_with_post_request(int $bytes): void
    {
        $key          = uniqid();
        $filePath     = __DIR__ . '/../Fixtures/data.txt';
        $fileName     = 'file.txt';
        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);
        $cacheMock    = m::mock(FileStore::class);
        $checksum     = hash_file('sha256', $filePath);

        if ($bytes === -1) {
            $data = file_get_contents($filePath);
        } else {
            $data = file_get_contents($filePath, false, null, 0, $bytes);
        }

        $cacheMock
            ->shouldReceive('set')
            ->once()
            ->with($key, [
                'location' => 'http://tus-server/files/' . $key,
                'expires_at' => 'Sat, 09 Dec 2017 00:00:00 GMT',
            ])
            ->andReturnNull();

        $cacheMock
            ->shouldReceive('getTtl')
            ->once()
            ->andReturn(86400);

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(201);

        $responseMock
            ->shouldReceive('getHeader')
            ->once()
            ->with('upload-offset')
            ->andReturn([\strlen($data)]);

        $responseMock
            ->shouldReceive('getHeader')
            ->once()
            ->with('location')
            ->andReturn(['http://tus-server/files/' . $key]);

        $this->tusClientMock
            ->shouldReceive('getChecksum')
            ->once()
            ->andReturn($checksum);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $this->tusClientMock
            ->shouldReceive('getCache')
            ->times(3)
            ->andReturn($cacheMock);

        $this->tusClientMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($key);

        $this->tusClientMock
            ->shouldReceive('getData')
            ->once()
            ->with(0, \strlen($data))
            ->andReturn($data);

        $this->tusClientMock->file($filePath, $fileName);

        $guzzleMock
            ->shouldReceive('post')
            ->once()
            ->with('/files', [
                'body' => $data,
                'headers' => [
                    'Upload-Length' => filesize($filePath),
                    'Upload-Key' => $key,
                    'Upload-Checksum' => 'sha256 ' . base64_encode($checksum),
                    'Upload-Metadata' => 'filename ' . base64_encode($fileName),
                    'Content-Type' => 'application/offset+octet-stream',
                    'Content-Length' => \strlen($data),
                ],
            ])
            ->andReturn($responseMock);

        $this->assertEquals(
            ['location' => 'http://tus-server/files/' . $key, 'offset' => \strlen($data)],
            ($bytes === -1) ?
                $this->tusClientMock->createWithUpload($key) :
                $this->tusClientMock->createWithUpload($key, $bytes)
        );
    }

    /**
     * @test
     *
     * @covers ::create
     * @covers ::createWithUpload
     */
    public function it_throws_exception_when_unable_to_create_resource(): void
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Unable to create resource.');

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

        $clientExceptionMock = m::mock(ClientException::class);
        $clientExceptionMock
            ->shouldReceive('getResponse')
            ->once()
            ->andReturn($responseMock);

        $guzzleMock
            ->shouldReceive('post')
            ->once()
            ->with('/files', [
                'body' => '',
                'headers' => [
                    'Upload-Length' => filesize($filePath),
                    'Upload-Key' => $key,
                    'Upload-Checksum' => 'sha256 ' . base64_encode($checksum),
                    'Upload-Metadata' => 'filename ' . base64_encode($fileName),
                ],
            ])
            ->andThrow($clientExceptionMock);

        $this->tusClientMock->create($key);
    }

    /**
     * @test
     *
     * @covers ::concat
     */
    public function it_creates_a_concat_resource_with_post_request(): void
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

        $msg  = json_encode(['data' => ['checksum' => $checksum]]);
        $body = class_exists(Utils::class) ? Utils::streamFor($msg) : $msg;
        $responseMock
            ->shouldReceive('getBody')
            ->once()
            ->andReturn($body);

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
     */
    public function it_throws_exception_when_unable_to_create_concat_resource(): void
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Unable to create resource.');

        $key          = uniqid();
        $filePath     = __DIR__ . '/../Fixtures/empty.txt';
        $fileName     = 'file.txt';
        $checksum     = hash_file('sha256', $filePath);
        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);
        $partials     = ['/files/a', '/files/b', 'files/c'];

        $body = class_exists(Utils::class) ? Utils::streamFor(null) : null;
        $responseMock
            ->shouldReceive('getBody')
            ->once()
            ->andReturn($body);

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
     */
    public function it_throws_exception_when_unable_to_get_checksum_in_concat(): void
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Unable to create resource.');

        $filePath     = __DIR__ . '/../Fixtures/empty.txt';
        $fileName     = 'file.txt';
        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);
        $partials     = ['/files/a', '/files/b', 'files/c'];

        $body = class_exists(Utils::class) ? Utils::streamFor(null) : null;
        $responseMock
            ->shouldReceive('getBody')
            ->once()
            ->andReturn($body);

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
    public function it_sends_a_delete_request(): void
    {
        $uploadKey    = '74f02d6da32';
        $guzzleMock   = m::mock(Client::class);
        $responseMock = m::mock(Response::class);

        $guzzleMock
            ->shouldReceive('delete')
            ->once()
            ->andReturn($responseMock);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $this->tusClientMock
            ->shouldReceive('getUrl')
            ->once()
            ->andReturn('http://tus-server/' . $uploadKey);

        $response = $this->tusClientMock->delete();

        $this->assertNull($response);
    }

    /**
     * @test
     *
     * @covers ::delete
     */
    public function it_throws_404_for_invalid_delete_request(): void
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('File not found.');

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
            ->andThrow($clientExceptionMock);

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(404);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $this->tusClientMock
            ->shouldReceive('getUrl')
            ->once()
            ->andReturn('http://tus-server/' . $uploadKey);

        $response = $this->tusClientMock->delete();

        $this->assertNull($response);
    }

    /**
     * @test
     *
     * @covers ::delete
     */
    public function it_throws_404_for_response_http_gone(): void
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('File not found.');

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
            ->andThrow($clientExceptionMock);

        $responseMock
            ->shouldReceive('getStatusCode')
            ->once()
            ->andReturn(410);

        $this->tusClientMock
            ->shouldReceive('getClient')
            ->once()
            ->andReturn($guzzleMock);

        $this->tusClientMock
            ->shouldReceive('getUrl')
            ->once()
            ->andReturn('http://tus-server/' . $uploadKey);

        $response = $this->tusClientMock->delete();

        $this->assertNull($response);
    }

    /**
     * @test
     *
     * @covers ::getData
     */
    public function it_gets_all_data(): void
    {
        $offset     = 0;
        $filePath   = __DIR__ . '/../Fixtures/data.txt';
        $dataLength = 92;

        $this->tusClientMock
            ->shouldReceive('getFilePath')
            ->once()
            ->andReturn($filePath);

        $data = $this->tusClientMock->getData($offset, $dataLength);

        $this->assertEquals($dataLength, \strlen($data));
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
    public function it_gets_partial_data(): void
    {
        $offset     = 49;
        $filePath   = __DIR__ . '/../Fixtures/data.txt';
        $dataLength = 15;

        $this->tusClientMock
            ->shouldReceive('getFilePath')
            ->once()
            ->andReturn($filePath);

        $data = $this->tusClientMock->getData($offset, $dataLength);

        $this->assertEquals($dataLength, \strlen($data));
        $this->assertEquals('Sherlock Holmes', $data);
    }

    /**
     * @test
     *
     * @covers ::getUploadChecksumHeader
     */
    public function it_gets_upload_checksum_header(): void
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
     * @test
     *
     * @covers ::getUploadMetadataHeader
     */
    public function it_gets_upload_metadata_header(): void
    {
        $filePath = __DIR__ . '/../Fixtures/empty.txt';

        $this->tusClientMock->file($filePath);

        $this->assertEquals('filename ' . base64_encode('empty.txt'), $this->tusClientMock->getUploadMetadataHeader());

        $this->tusClientMock->addMetadata('filename', 'test.txt');

        $metadata = 'filename ' . base64_encode('test.txt');
        $this->assertEquals($metadata, $this->tusClientMock->getUploadMetadataHeader());

        $this->tusClientMock->addMetadata('filetype', 'plain/text');

        $metadata .= ',filetype ' . base64_encode('plain/text');
        $this->assertEquals($metadata, $this->tusClientMock->getUploadMetadataHeader());
    }

    /**
     * Close mockery connection.
     *
     * @return void.
     */
    public function tearDown(): void
    {
        m::close();

        parent::tearDown();
    }
}

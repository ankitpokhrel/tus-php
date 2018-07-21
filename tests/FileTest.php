<?php

namespace TusPhp\Test;

use TusPhp\File;
use Mockery as m;
use phpmock\MockBuilder;
use TusPhp\Cache\FileStore;
use TusPhp\Cache\CacheFactory;
use PHPUnit\Framework\TestCase;
use TusPhp\Test\Fixtures\FileFixture;

/**
 * @coversDefaultClass \TusPhp\File
 */
class FileTest extends TestCase
{
    /** @var File */
    protected $file;

    /** @var MockBuilder */
    protected $mockBuilder;

    /**
     * Prepare vars.
     *
     * @return void
     */
    public function setUp()
    {
        $this->file        = new File('tus.txt', CacheFactory::make());
        $this->mockBuilder = (new MockBuilder)->setNamespace('\TusPhp');

        $this->file->setMeta(100, 1024, '/path/to/file.txt', 'http://tus.local/uploads/file.txt');

        parent::setUp();
    }

    /**
     * @test
     *
     * @covers ::__construct
     * @covers ::setMeta
     * @covers ::details
     */
    public function it_sets_meta_and_gets_details()
    {
        $this->file->setMeta(200, 2056, '/path/to/file.pdf', 'http://tus.local/uploads/file.pdf');

        $meta = $this->file->details();

        $this->assertEquals('tus.txt', $meta['name']);
        $this->assertEquals(2056, $meta['size']);
        $this->assertEquals(200, $meta['offset']);
        $this->assertEquals('http://tus.local/uploads/file.pdf', $meta['location']);
        $this->assertEquals('/path/to/file.pdf', $meta['file_path']);
        $this->assertEquals('Fri, 08 Dec 2017 00:00:00 GMT', $meta['created_at']);
        $this->assertEquals('Sat, 09 Dec 2017 00:00:00 GMT', $meta['expires_at']);
    }

    /**
     * @test
     *
     * @covers ::getName
     * @covers ::setName
     */
    public function it_sets_and_gets_name()
    {
        $this->assertEquals('tus.txt', $this->file->getName());

        $this->assertInstanceOf(File::class, $this->file->setName('file.txt'));
        $this->assertEquals('file.txt', $this->file->getName());
    }

    /**
     * @test
     *
     * @covers ::setFileSize
     * @covers ::getFileSize
     */
    public function it_sets_and_gets_file_size()
    {
        $this->assertEquals(1024, $this->file->getFileSize());

        $this->assertInstanceOf(File::class, $this->file->setFileSize(2056));
        $this->assertEquals(2056, $this->file->getFileSize());
    }

    /**
     * @test
     *
     * @covers ::getChecksum
     * @covers ::setChecksum
     */
    public function it_sets_and_gets_checksum()
    {
        $checksum = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';

        $this->assertInstanceOf(File::class, $this->file->setChecksum($checksum));
        $this->assertEquals($checksum, $this->file->getChecksum());
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

        $this->assertInstanceOf(File::class, $this->file->setKey($key));
        $this->assertEquals($key, $this->file->getKey());
    }

    /**
     * @test
     *
     * @covers ::getOffset
     * @covers ::setOffset
     */
    public function it_sets_and_gets_offset()
    {
        $this->assertEquals(100, $this->file->getOffset());
        $this->assertInstanceOf(File::class, $this->file->setOffset(500));
        $this->assertEquals(500, $this->file->getOffset());
    }

    /**
     * @test
     *
     * @covers ::getLocation
     * @covers ::setLocation
     */
    public function it_sets_and_gets_location()
    {
        $this->assertEquals('http://tus.local/uploads/file.txt', $this->file->getLocation());

        $location = 'http://tus.local/uploads/file.pdf';

        $this->assertInstanceOf(File::class, $this->file->setLocation($location));
        $this->assertEquals($location, $this->file->getLocation());
    }

    /**
     * @test
     *
     * @covers ::getFilePath
     * @covers ::setFilePath
     */
    public function it_sets_and_gets_file_path()
    {
        $this->assertEquals('/path/to/file.txt', $this->file->getFilePath());

        $filePath = '/path/to/file.pdf';

        $this->assertInstanceOf(File::class, $this->file->setFilePath($filePath));
        $this->assertEquals($filePath, $this->file->getFilePath());
    }

    /**
     * @test
     *
     * @covers ::getInputStream
     */
    public function it_gets_input_stream()
    {
        $this->assertEquals('php://input', $this->file->getInputStream());
    }

    /**
     * @test
     *
     * @covers ::open
     * @covers ::exists
     *
     * @expectedException \TusPhp\Exception\FileException
     * @expectedExceptionMessage File not found.
     */
    public function it_throws_404_for_invalid_file()
    {
        $this->file->open('/path/to/invalid/file.txt', 'rb');
    }

    /**
     * @test
     *
     * @covers ::open
     * @covers ::exists
     *
     * @expectedException \TusPhp\Exception\FileException
     * @expectedExceptionMessageRegExp  /Unable to open [a-zA-Z0-9-\/.]+/
     */
    public function it_throws_exception_for_file_with_no_permission()
    {
        $file = __DIR__ . '/Fixtures/403.txt';

        chmod($file, 0444);

        $this->file->open($file, 'w');
    }

    /**
     * @test
     *
     * @covers ::open
     * @covers ::exists
     * @covers ::close
     */
    public function it_opens_a_file()
    {
        $file = __DIR__ . '/Fixtures/empty.txt';

        $resource = $this->file->open($file, 'rb');

        $this->assertInternalType('resource', $resource);

        $this->file->close($resource);
    }

    /**
     * @test
     *
     * @covers ::exists
     */
    public function it_checks_if_file_exists_based_on_mode()
    {
        $this->assertTrue($this->file->exists('php://input'));
        $this->assertTrue($this->file->exists(__DIR__ . '/Fixtures/empty.txt'));
        $this->assertTrue($this->file->exists(__DIR__ . '/Fixtures/invalid.txt', 'wb+'));
    }

    /**
     * @test
     *
     * @covers ::exists
     *
     * @expectedException \TusPhp\Exception\FileException
     * @expectedExceptionMessage File not found.
     */
    public function it_throws_file_exception_if_file_doesnt_exists_for_read_mode()
    {
        $this->assertFalse($this->file->exists(__DIR__ . '/Fixtures/invalid.txt'));
    }

    /**
     * @test
     *
     * @covers ::seek
     *
     * @runInSeparateProcess
     *
     * @expectedException \TusPhp\Exception\FileException
     * @expectedExceptionMessage Cannot move pointer to desired position.
     */
    public function it_throws_exception_if_it_cannot_seek()
    {
        $this->mockBuilder
            ->setName('fseek')
            ->setFunction(
                function () {
                    return -1;
                }
            );

        $mock = $this->mockBuilder->build();

        $mock->enable();

        $file   = __DIR__ . '/Fixtures/empty.txt';
        $handle = $this->file->open($file, 'r');

        $this->file->seek($handle, 5);
        $this->file->close($handle);

        $mock->disable();
    }

    /**
     * @test
     *
     * @covers ::seek
     * @covers ::close
     */
    public function it_moves_to_proper_location_in_a_file()
    {
        $file = __DIR__ . '/Fixtures/data.txt';

        $handle = $this->file->open($file, 'r');

        $this->file->seek($handle, 5);

        $this->assertEquals(5, ftell($handle));

        $this->file->close($handle);
    }

    /**
     * @test
     *
     * @covers ::read
     *
     * @runInSeparateProcess
     *
     * @expectedException \TusPhp\Exception\FileException
     * @expectedExceptionMessage Cannot read file.
     */
    public function it_throws_exception_if_it_cannot_read()
    {
        $this->mockBuilder
            ->setName('fread')
            ->setFunction(
                function () {
                    return false;
                }
            );

        $mock = $this->mockBuilder->build();

        $mock->enable();

        $file   = __DIR__ . '/Fixtures/empty.txt';
        $handle = $this->file->open($file, 'r');

        $this->file->read($handle, 5);
        $this->file->close($handle);

        $mock->disable();
    }

    /**
     * @test
     *
     * @covers ::seek
     * @covers ::read
     * @covers ::close
     */
    public function it_reads_from_a_file()
    {
        $file = __DIR__ . '/Fixtures/data.txt';

        $handle = $this->file->open($file, 'r');

        $this->file->seek($handle, 49);

        $this->assertEquals('Sherlock Holmes', $this->file->read($handle, 15));

        $this->file->close($handle);
    }

    /**
     * @test
     *
     * @covers ::write
     *
     * @runInSeparateProcess
     *
     * @expectedException \TusPhp\Exception\FileException
     * @expectedExceptionMessage Cannot write to a file.
     */
    public function it_throws_exception_if_it_cannot_write()
    {
        $this->mockBuilder
            ->setName('fwrite')
            ->setFunction(
                function () {
                    return false;
                }
            );

        $mock = $this->mockBuilder->build();

        $mock->enable();

        $file   = __DIR__ . '/Fixtures/empty.txt';
        $handle = $this->file->open($file, 'r');

        $this->file->write($handle, '');
        $this->file->close($handle);

        $mock->disable();
    }

    /**
     * @test
     *
     * @covers ::open
     * @covers ::write
     * @covers ::read
     * @covers ::close
     */
    public function it_writes_to_a_file()
    {
        $file   = __DIR__ . '/.tmp/upload.txt';
        $handle = $this->file->open($file, 'w+');
        $bytes  = 15;

        $this->file->write($handle, 'Sherlock Holmes', $bytes);

        $this->assertEquals('', $this->file->read($handle, $bytes));

        $this->file->seek($handle, 0);

        $this->assertEquals('Sherlock Holmes', $this->file->read($handle, $bytes));

        $this->file->close($handle);

        @unlink($file);
    }

    /**
     * @test
     *
     * @covers ::merge
     *
     * @expectedException \TusPhp\Exception\FileException
     * @expectedExceptionMessage File to be merged not found.
     */
    public function it_throws_file_exception_if_file_to_merge_doesnt_exist()
    {
        $path  = __DIR__ . '/.tmp/fld';
        $files = [
            ['file_path' => "$path/1", 'offset' => 10],
        ];

        FileFixture::makeFilesAndFolder($path, $files);

        array_push($files, ['file_path' => "$path/invalid", 'offset' => 0]);

        $mergedFilePath = $path . '/../file.txt';

        $this->file->setFilePath($mergedFilePath)->merge($files);
    }

    /**
     * @test
     *
     * @covers ::copy
     * @covers ::merge
     */
    public function it_merges_two_or_more_files() : string
    {
        $path  = __DIR__ . '/.tmp/fld';
        $files = [
            ['file_path' => "$path/1", 'offset' => 10],
            ['file_path' => "$path/2", 'offset' => 20],
            ['file_path' => "$path/3", 'offset' => 30],
        ];

        FileFixture::makeFilesAndFolder($path, $files);

        $mergedFilePath = $path . '/../file.txt';

        $this->file->setFilePath($mergedFilePath)->merge($files);

        $this->assertTrue(file_exists($mergedFilePath));
        $this->assertEquals('123', file_get_contents($mergedFilePath));
        $this->assertEquals(60, $this->file->getOffset());
        $this->assertEquals(3, $this->file->getFileSize());

        @unlink($mergedFilePath);

        return $path;
    }

    /**
     * @test
     *
     * @depends it_merges_two_or_more_files
     *
     * @covers ::delete
     */
    public function it_deletes_all_files_and_folder(string $path)
    {
        $files = [
            "$path/1",
            "$path/2",
            "$path/3",
        ];

        $this->file->delete($files, true);

        $this->assertFalse(file_exists($path));
    }

    /**
     * @test
     *
     * @covers ::copy
     * @covers ::merge
     *
     * @expectedException \TusPhp\Exception\FileException
     * @expectedExceptionMessage Cannot copy source to destination.
     */
    public function it_throws_file_exception_if_it_cannot_copy_file()
    {
        $path  = __DIR__ . '/.tmp/fld';
        $files = [
            ['file_path' => "$path/invalid", 'offset' => 10],
        ];

        $this->file->merge($files);
    }

    /**
     * @test
     *
     * @covers ::delete
     * @covers ::deleteFiles
     */
    public function it_deletes_files_only() : string
    {
        $path = __DIR__ . '/.tmp/fld';

        $files = [
            "$path/1",
            "$path/2",
            "$path/3",
        ];

        FileFixture::makeFilesAndFolder($path, [
            ['file_path' => "$path/1", 'offset' => 10],
            ['file_path' => "$path/2", 'offset' => 20],
            ['file_path' => "$path/3", 'offset' => 30],
        ]);

        $this->file->delete($files);

        foreach ($files as $file) {
            $this->assertFalse(file_exists($file));
        }

        $this->assertTrue(file_exists($path));
        $this->assertTrue(is_dir($path));

        return $path;
    }

    /**
     * @test
     *
     * @depends it_deletes_files_only
     *
     * @covers ::delete
     * @covers ::deleteFiles
     */
    public function it_doesnt_delete_folder_if_file_path_is_not_given(string $path)
    {
        $this->file->delete([], true);

        $this->assertTrue(file_exists($path));
    }

    /**
     * @test
     *
     * @depends it_deletes_files_only
     *
     * @covers ::delete
     * @covers ::deleteFiles
     */
    public function it_deletes_empty_folder(string $path)
    {
        $this->file->delete(["$path/1"], true);

        $this->assertFalse(file_exists($path));
    }

    /**
     * @test
     *
     * @covers ::upload
     */
    public function it_throws_file_exception_if_already_uploaded()
    {
        $this->assertEquals(1000, $this->file->setOffset(1000)->upload(1000));
    }

    /**
     * @test
     *
     * @covers ::upload
     * @covers ::open
     *
     * @expectedException \TusPhp\Exception\FileException
     * @expectedExceptionMessageRegExp /Unable to open [a-zA-Z0-9-\/.]+/
     */
    public function it_throws_404_if_source_file_not_found()
    {
        $this->file->setOffset(10)->upload(100);
    }

    /**
     * @test
     *
     * @covers ::upload
     * @covers ::open
     *
     * @runInSeparateProcess
     *
     * @expectedException \TusPhp\Exception\ConnectionException
     * @expectedExceptionMessage Connection aborted by user.
     */
    public function it_throws_connection_exception_if_connection_is_aborted_by_user()
    {
        $file = __DIR__ . '/.tmp/upload.txt';
        $key  = uniqid();

        $cacheMock = m::mock(FileStore::class);
        $fileMock  = m::mock(File::class, [null, $cacheMock])->makePartial();

        $fileMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($key);

        $this->mockBuilder
            ->setName('connection_status')
            ->setFunction(
                function () use ($key) {
                    return 1;
                }
            );

        $mock = $this->mockBuilder->build();

        $mock->enable();

        $fileMock
            ->setFilePath($file)
            ->setOffset(0)
            ->upload(100);

        $mock->disable();

        @unlink($file);
    }

    /**
     * @test
     *
     * @covers ::upload
     *
     * @expectedException \TusPhp\Exception\OutOfRangeException
     * @expectedExceptionMessage The uploaded file is corrupt.
     */
    public function it_throws_exception_if_uploaded_file_is_corrupt()
    {
        $file  = __DIR__ . '/.tmp/upload.txt';
        $data  = file_get_contents(__DIR__ . '/Fixtures/data.txt');
        $key   = uniqid();
        $bytes = strlen($data);

        $cacheMock = m::mock(FileStore::class);
        $fileMock  = m::mock(File::class, [null, $cacheMock])->makePartial();

        $fileMock
            ->shouldReceive('read')
            ->once()
            ->andReturn($data);

        $fileMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($key);

        $cacheMock
            ->shouldReceive('set')
            ->once()
            ->with($key, ['offset' => $bytes])
            ->andReturn(null);

        $fileMock
            ->setFilePath($file)
            ->setOffset(0)
            ->upload($bytes - 1);

        @unlink($file);
    }

    /**
     * @test
     *
     * @covers ::upload
     *
     * @runInSeparateProcess
     */
    public function it_uploads_a_chunk()
    {
        $file       = __DIR__ . '/.tmp/upload.txt';
        $dataFile   = __DIR__ . '/Fixtures/large.txt';
        $key        = uniqid();
        $checksum   = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $chunkSize  = 8192;
        $totalBytes = 17548;

        $cacheMock = m::mock(FileStore::class);
        $fileMock  = m::mock(File::class, [null, $cacheMock])->makePartial();

        $fileMock
            ->shouldReceive('getInputStream')
            ->once()
            ->andReturn($dataFile);

        $fileMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($key);

        $cacheMock
            ->shouldReceive('set')
            ->once()
            ->with($key, ['offset' => $chunkSize])
            ->andReturn(null);

        $cacheMock
            ->shouldReceive('set')
            ->once()
            ->with($key, ['offset' => $chunkSize * 2])
            ->andReturn(null);

        $cacheMock
            ->shouldReceive('set')
            ->once()
            ->with($key, ['offset' => $totalBytes])
            ->andReturn(null);

        $this->mockBuilder
            ->setName('hash_file')
            ->setFunction(
                function () use ($checksum) {
                    return $checksum;
                }
            );

        $mock = $this->mockBuilder->build();

        $mock->enable();

        $bytesWritten = $fileMock
            ->setFilePath($file)
            ->setOffset(0)
            ->upload($totalBytes);

        $this->assertEquals($totalBytes, $bytesWritten);

        $mock->disable();

        @unlink($file);
    }

    /**
     * @test
     *
     * @covers ::upload
     *
     * @runInSeparateProcess
     */
    public function it_uploads_complete_file()
    {
        $file       = __DIR__ . '/.tmp/upload.txt';
        $data       = file_get_contents(__DIR__ . '/Fixtures/data.txt');
        $key        = uniqid();
        $checksum   = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $totalBytes = strlen($data);

        $cacheMock = m::mock(FileStore::class);
        $fileMock  = m::mock(File::class, [null, $cacheMock])->makePartial();

        $fileMock
            ->shouldReceive('read')
            ->once()
            ->andReturn($data);

        $fileMock
            ->shouldReceive('getKey')
            ->once()
            ->andReturn($key);

        $cacheMock
            ->shouldReceive('set')
            ->once()
            ->with($key, ['offset' => $totalBytes])
            ->andReturn(null);

        $this->mockBuilder
            ->setName('hash_file')
            ->setFunction(
                function () use ($checksum) {
                    return $checksum;
                }
            );

        $mock = $this->mockBuilder->build();

        $mock->enable();

        $bytesWritten = $fileMock
            ->setFilePath($file)
            ->setOffset(0)
            ->upload($totalBytes);

        $this->assertEquals($totalBytes, $bytesWritten);

        $mock->disable();

        @unlink($file);
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

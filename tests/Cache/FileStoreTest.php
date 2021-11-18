<?php

namespace TusPhp\Test\Cache;

use TusPhp\Config;
use TusPhp\Cache\FileStore;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \TusPhp\Cache\FileStore
 */
class FileStoreTest extends TestCase
{
    /** @var string */
    protected $checksum;

    /** @var string */
    protected $cacheDir;

    /** @var string */
    protected $cacheFile;

    /** @var FileStore */
    protected $fileStore;

    /**
     * Prepare vars.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->checksum  = '74f02d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3b3a';
        $this->cacheDir  = Config::get('file.dir');
        $this->cacheFile = Config::get('file.name');
        $this->fileStore = new FileStore();

        $this->fileStore
            ->setCacheDir($this->cacheDir)
            ->setCacheFile($this->cacheFile);

        parent::setUp();
    }

    /**
     * @test
     *
     * @covers ::getTtl
     * @covers ::setTtl
     */
    public function it_sets_and_gets_ttl(): void
    {
        $this->assertEquals(86400, $this->fileStore->getTtl());

        $this->fileStore->setTtl(10);

        $this->assertEquals(10, $this->fileStore->getTtl());
    }

    /**
     * @test
     *
     * @covers ::setPrefix
     * @covers ::getPrefix
     */
    public function it_sets_and_gets_file_cache_prefix(): void
    {
        $this->assertEquals('tus:', $this->fileStore->getPrefix());
        $this->assertInstanceOf(FileStore::class, $this->fileStore->setPrefix('file:'));
        $this->assertEquals('file:', $this->fileStore->getPrefix());
    }

    /**
     * @test
     *
     * @covers ::__construct
     * @covers ::getCacheFile
     */
    public function it_sets_default_cache_dir_and_file(): void
    {
        $this->assertEquals(Config::get('file.dir') . Config::get('file.name'), (new FileStore())->getCacheFile());
    }

    /**
     * @test
     *
     * @covers ::__construct
     * @covers ::getCacheFile
     */
    public function it_sets_cache_dir_and_file(): void
    {
        $cacheDir  = '.temp' . DS;
        $cacheFile = 'cache_file.cache';

        $fileCache = new FileStore($cacheDir);
        $fileStore = new FileStore($cacheDir, $cacheFile);

        $this->assertEquals($cacheDir . 'tus_php.cache', $fileCache->getCacheFile());
        $this->assertEquals($cacheDir . $cacheFile, $fileStore->getCacheFile());
    }

    /**
     * @test
     *
     * @covers ::setCacheDir
     * @covers ::getCacheDir
     */
    public function it_sets_and_gets_cache_dir(): void
    {
        $cacheDir = '/path/to/cache/dir';

        $this->assertInstanceOf(FileStore::class, $this->fileStore->setCacheDir($cacheDir));
        $this->assertEquals($cacheDir, $this->fileStore->getCacheDir());
    }

    /**
     * @test
     *
     * @covers ::setCacheFile
     * @covers ::getCacheFile
     */
    public function it_sets_and_gets_cache_file(): void
    {
        $cacheFile       = 'tus_cache.txt';
        $defaultCacheDir = Config::get('file.dir');

        $fileStore = new FileStore();

        $this->assertInstanceOf(FileStore::class, $fileStore->setCacheFile($cacheFile));
        $this->assertEquals($defaultCacheDir . $cacheFile, $fileStore->getCacheFile());
    }

    /**
     * @test
     *
     * @covers ::getCacheFile
     * @covers ::createCacheFile
     * @covers ::createCacheDir
     * @covers ::isValid
     */
    public function it_creates_cache_file_if_file_does_not_exist(): void
    {
        $this->fileStore->set($this->checksum, 'Test');

        $this->assertFileExists($this->cacheDir);
        $this->assertFileExists($this->cacheDir.$this->cacheFile);

        // Cache is invalid, should return null.
        $this->assertEquals(null, $this->fileStore->get($this->checksum));
    }

    /**
     * @test
     *
     * @covers ::getCacheFile
     * @covers ::createCacheFile
     * @covers ::createCacheDir
     * @covers ::isValid
     * @covers ::set
     * @covers ::get
     * @covers ::put
     */
    public function it_sets_cache_contents(): void
    {
        $cacheContent = ['expires_at' => 'Fri, 08 Dec 2017 16:25:51 GMT', 'offset' => 0];

        $this->fileStore->set($this->checksum, $cacheContent);

        $this->assertFileExists($this->cacheDir);
        $this->assertFileExists($this->cacheDir.$this->cacheFile);
        $this->assertEquals($cacheContent, $this->fileStore->get($this->checksum));
    }

    /**
     * @test
     *
     * @covers ::set
     * @covers ::get
     * @covers ::put
     */
    public function it_doesnt_replace_cache_key_in_set(): void
    {
        $cacheContent = ['expires_at' => 'Sat, 09 Dec 2017 16:25:51 GMT', 'offset' => 100];

        $this->fileStore->set($this->checksum, $cacheContent);

        $this->assertEquals($cacheContent, $this->fileStore->get($this->checksum));

        $this->fileStore->set($this->checksum, ['offset' => 500]);

        $contents = $this->fileStore->get($this->checksum);

        $this->assertEquals(500, $contents['offset']);
        $this->assertEquals('Sat, 09 Dec 2017 16:25:51 GMT', $contents['expires_at']);
    }

    /**
     * @test
     *
     * @covers ::get
     */
    public function it_returns_null_if_cache_key_doesnt_exist(): void
    {
        $content = $this->fileStore->get($this->checksum);

        $this->assertNull($content);
    }

    /**
     * @test
     *
     * @covers ::get
     * @covers ::set
     */
    public function it_returns_null_for_invalid_expiry_key(): void
    {
        $cacheContent = ['expires_at' => '', 'offset' => 100];

        $this->fileStore->set($this->checksum, $cacheContent);

        $this->assertNull($this->fileStore->get($this->checksum));

        $cacheContent = ['expires_at' => 'Thu, 07 Dec 2017 16:25:51 GMT'];
        $this->fileStore->set($this->checksum, $cacheContent);

        $this->assertNull($this->fileStore->get($this->checksum));
    }

    /**
     * @test
     *
     * @covers ::get
     */
    public function it_returns_expired_contents_if_with_expired_is_true(): void
    {
        $cacheContent = ['expires_at' => 'Thu, 07 Dec 2017 16:25:51 GMT', 'offset' => 100];

        $this->fileStore->set($this->checksum, $cacheContent);

        $this->assertNull($this->fileStore->get($this->checksum));
        $this->assertEquals($cacheContent, $this->fileStore->get($this->checksum, true));
    }

    /**
     * @test
     *
     * @covers ::set
     * @covers ::get
     */
    public function it_gets_cache_content(): void
    {
        $cacheContent = ['expires_at' => 'Fri, 08 Dec 2017 16:25:51 GMT', 'offset' => 100];

        $this->fileStore->set($this->checksum, $cacheContent);

        $this->assertEquals($cacheContent, $this->fileStore->get($this->checksum));
    }

    /**
     * @test
     *
     * @covers ::set
     * @covers ::isValid
     */
    public function it_checks_cache_expiry_date(): void
    {
        $cacheContent = ['expires_at' => 'Fri, 08 Dec 2017 16:25:51 GMT'];

        $this->fileStore->set($this->checksum, $cacheContent);

        $this->assertTrue($this->fileStore->isValid($this->checksum));

        $cacheContent = ['expires_at' => 'Thu, 07 Dec 2017 16:25:51 GMT'];
        $this->fileStore->set($this->checksum, $cacheContent);

        $this->assertFalse($this->fileStore->isValid($this->checksum));
    }

    /**
     * @test
     *
     * @covers ::getCacheContents
     */
    public function it_returns_false_if_cache_file_doesnt_exist(): void
    {
        $this->assertFalse($this->fileStore->getCacheContents());
    }

    /**
     * @test
     *
     * @covers ::set
     * @covers ::put
     * @covers ::lock
     * @covers ::getCacheContents
     */
    public function it_gets_all_cache_contents(): void
    {
        $cacheContent = ['expires_at' => 'Fri, 08 Dec 2017 16:25:51 GMT', 'offset' => 100];

        $this->fileStore->set($this->checksum, $cacheContent);

        $checksum = 'a8502d6da32082463e382f2274e85fd8eae3e81f739f8959abc91865656e3u01';
        $content  = ['size' => 200];
        $expected = ['tus:' . $this->checksum => $cacheContent] + ['tus:' . $checksum => $content];

        $this->fileStore->set($checksum, $content);

        $this->assertEquals($expected, $this->fileStore->getCacheContents());
    }

    /**
     * @test
     *
     * @covers ::set
     * @covers ::get
     * @covers ::delete
     * @covers ::put
     * @covers ::getCacheContents
     */
    public function it_deletes_cache_content(): void
    {
        $cacheContent = ['expires_at' => 'Fri, 08 Dec 2017 16:25:51 GMT', 'offset' => 100];

        $this->fileStore->set($this->checksum, $cacheContent);

        $this->assertEquals(['tus:' . $this->checksum => $cacheContent], $this->fileStore->getCacheContents());

        $this->assertFalse($this->fileStore->delete('invalid-checksum'));
        $this->assertTrue($this->fileStore->delete($this->checksum));
        $this->assertNull($this->fileStore->get($this->checksum));
    }

    /**
     * @test
     *
     * @covers ::set
     * @covers ::get
     * @covers ::deleteAll
     */
    public function it_deletes_all_cache_keys(): void
    {
        $checksum1    = 'checksum-1';
        $checksum2    = 'checksum-2';
        $cacheContent = ['expires_at' => 'Fri, 08 Dec 2017 16:25:51 GMT', 'offset' => 100];

        $this->fileStore->set($checksum1, $cacheContent);
        $this->fileStore->set($checksum2, $cacheContent);

        $this->assertTrue($this->fileStore->deleteAll([$checksum1, $checksum2]));
        $this->assertFalse($this->fileStore->deleteAll([]));
        $this->assertNull($this->fileStore->get($checksum1));
        $this->assertNull($this->fileStore->get($checksum2));
    }

    /**
     * @test
     *
     * @covers ::keys
     */
    public function it_gets_cache_keys(): void
    {
        $this->fileStore->set($this->checksum, []);

        $this->assertEquals(['tus:' . $this->checksum], $this->fileStore->keys());
    }

    /**
     * @test
     *
     * @covers ::keys
     */
    public function it_returns_empty_array_for_invalid_cache_contents(): void
    {
        $this->fileStore->setCacheFile('/path/to/invalid/file');

        $this->assertEquals([], $this->fileStore->keys());
    }

    /**
     * @test
     *
     * @covers ::getActualCacheKey
     * @covers ::setPrefix
     */
    public function it_gets_actual_cache_key(): void
    {
        $this->assertEquals('tus:cache-key', $this->fileStore->getActualCacheKey('cache-key'));
        $this->assertEquals('tus:cache-key', $this->fileStore->getActualCacheKey('tus:cache-key'));

        $this->assertInstanceOf(FileStore::class, $this->fileStore->setPrefix('hello:'));

        $this->assertEquals('hello:cache-key', $this->fileStore->getActualCacheKey('cache-key'));
        $this->assertEquals('hello:cache-key', $this->fileStore->getActualCacheKey('hello:cache-key'));
    }

    /**
     * @test
     *
     * @covers ::put
     * @covers ::lock
     * @covers ::sharedGet
     */
    public function it_gets_data_with_shared_lock(): void
    {
        $filePath  = __DIR__ . '/../.tmp/shared.txt';
        $fileStore = new FileStore(__DIR__ . '/../.tmp/', 'shared.txt');

        $contents = '';
        for ($i = 0; $i < 10000; $i++) {
            $contents .= $i;
        }

        touch($filePath);

        for ($i = 0; $i < 20; $i++) {
            $pid = pcntl_fork();

            if ( ! $pid) { // Child process.
                usleep($i);

                $fileStore->put($filePath, $contents);

                exit($fileStore->sharedGet($filePath) === $contents ? 0 : 1);
            }
        }

        while (-1 !== pcntl_waitpid(0, $status)) {
            $status = pcntl_wexitstatus($status);

            $this->assertSame($status, 0);
        }

        @unlink($filePath);
    }

    /**
     * @test
     *
     * @covers ::lock
     * @covers ::sharedGet
     */
    public function it_gets_contents_in_shared_get(): void
    {
        $this->assertEmpty($this->fileStore->sharedGet(__DIR__ . '/.tmp/invalid.file'));

        $file  = __DIR__ . '/../.tmp/shared.txt';
        $text  = 'lorem ipsum';

        file_put_contents($file, $text);

        $this->assertEquals($text, $this->fileStore->sharedGet($file));

        @unlink($file);
    }

    /**
     * Clear file contents.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if (file_exists($this->cacheDir . $this->cacheFile)) {
            unlink($this->cacheDir . $this->cacheFile);
            rmdir($this->cacheDir);
        }

        parent::tearDown();
    }
}

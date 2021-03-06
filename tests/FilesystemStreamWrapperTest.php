<?php

class FilesystemStreamWrapperTest extends PHPUnit_Framework_TestCase
{
    private $testRootPath;

    public function setUp()
    {
        $this->testRootPath = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'var';

        if (!@mkdir($this->testRootPath) && !is_dir($this->testRootPath)) {
            throw new RuntimeException("Unable to create test:// root path: {$this->testRootPath}");
        }
        FilesystemStreamWrapper::register('test', $this->testRootPath);
    }

    public function tearDown()
    {
        FilesystemStreamWrapper::unregister('test');
        foreach (array_diff(scandir($this->testRootPath), array('.', '..')) as $path) {
            if (is_dir($this->testRootPath . DIRECTORY_SEPARATOR . $path)) {
                self::rmdirr($this->testRootPath . DIRECTORY_SEPARATOR . $path);
            } else {
                unlink($this->testRootPath . DIRECTORY_SEPARATOR . $path);
            }
        }
    }

    public static function rmdirr(string $dir) : bool
    {
        foreach (array_diff(scandir($dir), array('.', '..')) as $file) {
            if (is_dir("$dir/$file")) {
                self::rmdirr("$dir/$file");
            } else {
                unlink("$dir/$file");
            }
        }
        return rmdir($dir);
    }

    public function testRegistration()
    {
        FilesystemStreamWrapper::register('test1', $this->testRootPath);
        $this->assertTrue(in_array('test1', stream_get_wrappers()));
        FilesystemStreamWrapper::register('test2', $this->testRootPath);
        $this->assertTrue(in_array('test2', stream_get_wrappers()));

        FilesystemStreamWrapper::unregister('test1');
        $this->assertFalse(in_array('test1', stream_get_wrappers()));
        FilesystemStreamWrapper::unregister('test2');
        $this->assertFalse(in_array('test2', stream_get_wrappers()));
    }

    /**
     * Tests if the stream wrapper returns the expected result when reading a directory.
     */
    public function testDirectoryRead()
    {
        touch('test://file.txt');
        mkdir('test://directory');
        $dir = opendir('test://');
        $entries = [];
        while ($entry = readdir($dir)) {
            $entries[] = $entry;
        }
        $this->assertCount(0, array_diff(['.', 'directory', '..', 'file.txt'], $entries));
    }

    /**
     * Tests if the stream wrapper returns the expected result when rewinding a directory handle.
     */
    public function testDirectoryRewind()
    {
        touch('test://file.ext');
        $directoryResource = opendir('test://');
        $entry = readdir($directoryResource);
        rewinddir($directoryResource);
        $this->assertSame($entry, readdir($directoryResource));
    }

    /**
     * Tests if the stream wrapper creates a directory.
     */
    public function testDirectoryCreateAndRemove()
    {
        $this->assertTrue(mkdir('test://directory/in-a-directory', 0777, true));
        $this->assertTrue(rmdir('test://directory/in-a-directory'));
    }

    /**
     * Tests if the stream wrapper touches a file.
     */
    public function testTouch()
    {
        mkdir('test://directory');
        $this->assertTrue(touch('test://directory/with-a-file.ext'));
        $this->assertFileExists($this->testRootPath . '/directory/with-a-file.ext');
    }

    /**
     * Tests if the stream wrapper unlinks a file.
     */
    public function testUnlink()
    {
        mkdir('test://directory');
        touch('test://directory/with-a-file.ext');
        $this->assertTrue(unlink('test://directory/with-a-file.ext'));
        $this->assertFileNotExists($this->testRootPath . '/directory/with-a-file.ext');
    }

    /**
     * Tests if the stream wrapper writes to a file.
     *
     * @depends testUnlink
     */
    public function testReadAndWriteFile()
    {
        $this->assertSame(9, file_put_contents('test://file.ext', "contents\n"));
        $this->assertFileExists($this->testRootPath . '/file.ext');
        $this->assertSame("contents\n", file_get_contents('test://file.ext'));
    }

    /**
     * Tests if the stream wrapper renames a file.
     */
    public function testRename()
    {
        touch('test://file.ext');
        $this->assertFileExists($this->testRootPath . '/file.ext');
        $this->assertTrue(rename('test://file.ext', 'test://file.ext2'));
        $this->assertFileNotExists($this->testRootPath . '/file.ext');
        $this->assertFileExists($this->testRootPath . '/file.ext2');
    }
}

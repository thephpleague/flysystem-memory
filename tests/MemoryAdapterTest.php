<?php

namespace Twistor\Tests;

use League\Flysystem\Adapter\Local;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use Twistor\Flysystem\MemoryAdapter;

/**
 * @coversDefaultClass \Twistor\Flysystem\MemoryAdapter
 */
class MemoryAdapterTest  extends \PHPUnit_Framework_TestCase
{
    protected $adapter;

    public function setUp()
    {
        $this->adapter = new MemoryAdapter();
        $this->adapter->write('file.txt', 'contents', new Config());
    }

    /**
     * @covers ::copy
     */
    public function testCopy()
    {
        $this->assertTrue($this->adapter->copy('file.txt', 'dir/new_file.txt'));
        $this->assertSame('contents', $this->adapter->read('dir/new_file.txt', new Config())['contents']);

        $this->assertFalse($this->adapter->copy('file.txt', 'dir/new_file.txt/other.txt'));
    }

    /**
     * @covers ::createDir
     */
    public function testCreateDir()
    {
        $result = $this->adapter->createDir('dir/subdir', new Config());

        $this->assertSame(2, count($result));
        $this->assertSame('dir/subdir', $result['path']);
        $this->assertSame('dir', $result['type']);
        $this->assertTrue($this->adapter->has('dir'));
        $this->assertTrue($this->adapter->has('dir/subdir'));

        $this->assertFalse($this->adapter->createDir('file.txt', new Config()));
        $this->assertFalse($this->adapter->createDir('file.txt/dir', new Config()));
    }

    /**
     * @covers ::delete
     */
    public function testDelete()
    {
        $this->assertTrue($this->adapter->delete('file.txt'));
        $this->assertFalse($this->adapter->has('file.txt'));
        $this->assertFalse($this->adapter->delete('file.txt'));
    }

    /**
     * @covers ::deleteDir
     */
    public function testDeleteDir()
    {
        $this->adapter->createDir('dir/subdir', new Config());
        $this->assertTrue($this->adapter->deleteDir('dir'));
        $this->assertFalse($this->adapter->has('dir/subdir'));
        $this->assertFalse($this->adapter->has('dir'));

        $this->assertFalse($this->adapter->deleteDir('dir'));
    }

    /**
     * @covers ::getMetaData
     */
    public function testGetMetadata()
    {
        $meta = $this->adapter->getMetadata('file.txt');

        $this->assertSame(6, count($meta));
        $this->assertSame('file.txt', $meta['path']);
        $this->assertSame('file', $meta['type']);
        $this->assertSame(8, $meta['size']);
        $this->assertSame('public', $meta['visibility']);
        $this->assertSame('text/plain', $meta['mimetype']);
        $this->assertTrue(is_int($meta['timestamp']));
    }

    /**
     * @covers ::getMimetype
     */
    public function testGetMimetype()
    {
        $meta = $this->adapter->getMimetype('file.txt');
        $this->assertSame('text/plain', $meta['mimetype']);
    }

    /**
     * @covers ::getSize
     */
    public function testGetSize()
    {
        $meta = $this->adapter->getSize('file.txt');
        $this->assertSame(8, $meta['size']);
    }

    /**
     * @covers ::getTimestamp
     */
    public function testGetTimestamp()
    {
        $meta = $this->adapter->getTimestamp('file.txt');
        $this->assertTrue(is_int($meta['timestamp']));
    }

    /**
     * @covers ::has
     */
    public function testHas()
    {
        $this->assertTrue($this->adapter->has('file.txt'));
        $this->assertFalse($this->adapter->has('no_file.txt'));
    }

    /**
     * @covers ::listContents
     */
    public function testListContents()
    {
        $result = $this->adapter->listContents('');
        $this->assertSame(1, count($result));
        $this->assertSame('file.txt', $result[0]['path']);

        $this->adapter->write('dir/file.txt', 'contents', new Config());
        $this->adapter->write('dir/subdir/file.txt', 'contents', new Config());

        $result = $this->adapter->listContents('', true);
        $this->assertSame(5, count($result));
        $this->assertSame('file.txt', $result[0]['path']);
        $this->assertSame('dir', $result[1]['path']);
        $this->assertSame('dir/file.txt', $result[2]['path']);
        $this->assertSame('dir/subdir', $result[3]['path']);
        $this->assertSame('dir/subdir/file.txt', $result[4]['path']);

        $result = $this->adapter->listContents('');
        $this->assertSame(2, count($result));
        $this->assertSame('file.txt', $result[0]['path']);
        $this->assertSame('dir', $result[1]['path']);

        $result = $this->adapter->listContents('dir', true);
        $this->assertSame(3, count($result));
        $this->assertSame('dir/file.txt', $result[0]['path']);
        $this->assertSame('dir/subdir', $result[1]['path']);
        $this->assertSame('dir/subdir/file.txt', $result[2]['path']);

        $result = $this->adapter->listContents('dir');
        $this->assertSame(2, count($result));
        $this->assertSame('dir/file.txt', $result[0]['path']);
        $this->assertSame('dir/subdir', $result[1]['path']);
    }

    /**
     * @covers ::read
     */
    public function testRead()
    {
        $this->assertSame('contents', $this->adapter->read('file.txt')['contents']);
        $this->assertSame('file.txt', $this->adapter->read('file.txt')['path']);
    }

    /**
     * @covers ::readStream
     */
    public function testReadStream()
    {
        $result = $this->adapter->readStream('file.txt');

        $this->assertSame('contents', stream_get_contents($result['stream']));
        $this->assertSame('file.txt', $result['path']);
    }

    /**
     * @covers ::rename
     */
    public function testRename()
    {
        $this->assertTrue($this->adapter->rename('file.txt', 'dir/subdir/file.txt'));
        $this->assertTrue($this->adapter->has('dir'));
        $this->assertTrue($this->adapter->has('dir/subdir'));

        $this->assertFalse($this->adapter->rename('dir/subdir/file.txt', 'dir/subdir/file.txt/new_file.txt'));
    }

    /**
     * @covers ::setVisibility
     */
    public function testSetVisibility()
    {
        $result = $this->adapter->setVisibility('file.txt', 'private');
        $this->assertSame('private', $result['visibility']);
        $this->assertSame('private', $this->adapter->getVisibility('file.txt')['visibility']);

        $this->assertFalse($this->adapter->setVisibility('no_file.txt', 'public'));
    }

    /**
     * @covers ::update
     */
    public function testUpdate()
    {
        $result = $this->adapter->update('file.txt', 'new contents', new Config(['visibility' => 'private']));
        $this->assertSame('new contents', $result['contents']);
        $this->assertSame('file.txt', $result['path']);
        $this->assertSame('private', $result['visibility']);
        $this->assertSame('new contents', $this->adapter->read('file.txt', new Config())['contents']);

        $this->assertFalse($this->adapter->update('new_file.txt', 'contents', new Config()));
    }

    /**
     * @covers ::write
     */
    public function testWrite()
    {
        $result = $this->adapter->write('new_file.txt', 'new contents', new Config());
        $this->assertSame('new contents', $result['contents']);
        $this->assertFalse($this->adapter->write('file.txt/new_file.txt', 'contents', new Config()));
    }

    public function testCreateFromFilesystem()
    {
        @unlink(__DIR__ . '/tmp/tmpfile.txt');
        @rmdir(__DIR__ . '/tmp');
        mkdir(__DIR__ . '/tmp');
        touch(__DIR__ . '/tmp/tmpfile.txt');

        // Test createFromFilesystem().
        $adapter = MemoryAdapter::createFromFilesystem(new Filesystem(new Local(__DIR__)));
        $contents = $adapter->listContents('', true);

        usort($contents, function ($a, $b) {
            return strcmp($a['path'], $b['path']);
        });

        $this->assertSame(3, count($contents));

        $this->assertSame('MemoryAdapterTest.php', $contents[0]['path']);
        $this->assertSame('tmp', $contents[1]['path']);
        $this->assertSame('tmp/tmpfile.txt', $contents[2]['path']);

        $this->assertSame(file_get_contents(__FILE__), $adapter->read('MemoryAdapterTest.php')['contents']);

        // Test createFromPath().
        $adapter = MemoryAdapter::createFromPath(__DIR__);
        $contents = $adapter->listContents('', true);

        usort($contents, function ($a, $b) {
            return strcmp($a['path'], $b['path']);
        });

        $this->assertSame(3, count($contents));

        $this->assertSame('MemoryAdapterTest.php', $contents[0]['path']);
        $this->assertSame('tmp', $contents[1]['path']);
        $this->assertSame('tmp/tmpfile.txt', $contents[2]['path']);

        $this->assertSame(file_get_contents(__FILE__), $adapter->read('MemoryAdapterTest.php')['contents']);

        unlink(__DIR__ . '/tmp/tmpfile.txt');
        rmdir(__DIR__ . '/tmp');
    }

    /**
     * @expectedException LogicException
     */
    public function testCreateFromFilesystemFail()
    {
        MemoryAdapter::createFromPath('does not exist');
    }

    /**
     * @expectedException LogicException
     */
    public function testCreateFromFilesystemFail2()
    {
        MemoryAdapter::createFromPath(__FILE__);
    }
}

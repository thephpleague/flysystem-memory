<?php

namespace Twistor\Tests;

use League\Flysystem\Adapter\Local;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use Twistor\Flysystem\MemoryAdapter;

class MemoryAdapterTest  extends \PHPUnit_Framework_TestCase
{
    public function test()
    {
        $adapter = new MemoryAdapter();
        $config = new Config(['visibility' => 'private']);

        $this->assertSame('dir', $adapter->getMetadata('')['type']);
        $this->assertSame('', $adapter->getMetadata('')['path']);

        $this->assertFalse($adapter->has('pics'));
        $this->assertFalse($adapter->read('pics/test.jpg'));
        $this->assertFalse($adapter->delete('pics/test.jpg'));
        $this->assertFalse($adapter->deleteDir('pics'));
        $this->assertFalse($adapter->readStream('pics/test.jpg'));
        $this->assertFalse($adapter->getMetadata('pics/test.jpg'));
        $this->assertFalse($adapter->getSize('pics/test.jpg'));
        $this->assertFalse($adapter->getTimestamp('pics/test.jpg'));
        $this->assertFalse($adapter->getMimetype('pics/test.jpg'));
        $this->assertFalse($adapter->getVisibility('pics/test.jpg'));
        $this->assertFalse($adapter->setVisibility('pics/test.jpg', 'asdfasf'));
        $this->assertFalse($adapter->update('pics/test.jpg', 'image content', $config));

        $this->assertSame('pics/test.jpg', $adapter->write('pics/test.jpg', 'image content', $config)['path']);
        $this->assertFalse($adapter->write('pics/test.jpg/new.jpg', 'image content', $config));
        $this->assertSame('image content', $adapter->read('pics/test.jpg')['contents']);
        $this->assertTrue($adapter->has('pics'));

        $this->assertSame('pics/test.jpg', $adapter->getMetadata('pics/test.jpg')['path']);
        $this->assertSame('file', $adapter->getMetadata('pics/test.jpg')['type']);
        $this->assertSame(strlen('image content'), $adapter->getSize('pics/test.jpg')['size']);
        $this->assertTrue(is_int($adapter->getTimestamp('pics/test.jpg')['timestamp']));
        $this->assertSame('image/jpeg', $adapter->getMimetype('pics/test.jpg')['mimetype']);

        $this->assertSame('private', $adapter->getVisibility('pics/test.jpg')['visibility']);
        $this->assertSame('public', $adapter->setVisibility('pics/test.jpg', 'public')['visibility']);
        $this->assertSame('public', $adapter->getVisibility('pics/test.jpg')['visibility']);

        $this->assertInternalType('array', $adapter->update('pics/test.jpg', 'updated image content', $config));
        $this->assertSame('updated image content', $adapter->read('pics/test.jpg')['contents']);

        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, 'new image content');
        rewind($stream);
        $this->assertFalse($adapter->writeStream('pics/test.jpg', $stream, $config));
        rewind($stream);
        $this->assertSame('file', $adapter->updateStream('pics/test.jpg', $stream, $config)['type']);
        fclose($stream);

        $this->assertSame('new image content', stream_get_contents($adapter->readStream('pics/test.jpg')['stream']));
        $this->assertTrue($adapter->rename('pics/test.jpg', 'photos/test.jpg'));
        $this->assertFalse($adapter->rename('photos/test.jpg', 'photos/test.jpg/new.jpg'));
        $this->assertTrue($adapter->has('photos/test.jpg'));
        $this->assertTrue($adapter->has('photos'));
        $this->assertFalse($adapter->has('pics/test.jpg'));

        // Test invalid copy.
        $this->assertFalse($adapter->copy('photos/test.jpg', 'photos/test.jpg/subfolder/new.jpg'));

        $listing = [['type' => 'dir', 'path' => 'pics'], ['type' => 'dir', 'path' => 'photos']];
        $this->assertSame($listing, $adapter->listContents());
        $listing[] = ['type' => 'file', 'path' => 'photos/test.jpg'];
        $this->assertSame($listing, $adapter->listContents('', true));
        $this->assertSame([['type' => 'file', 'path' => 'photos/test.jpg']], $adapter->listContents('photos'));

        $adapter->write('photos/vacation/fun.jpg', 'fun', $config);
        $listing = [
            ['type' => 'file', 'path' => 'photos/test.jpg'],
            ['type' => 'dir', 'path' => 'photos/vacation'],
            ['type' => 'file', 'path' => 'photos/vacation/fun.jpg'],
        ];

        $this->assertSame($listing, $adapter->listContents('photos', true));
        $this->assertTrue($adapter->delete('photos/test.jpg'));
        $this->assertTrue($adapter->deleteDir('photos'));
        $this->assertSame([], $adapter->listContents('photos'));
        $this->assertSame([['type' => 'dir', 'path' => 'pics']], $adapter->listContents('', true));

        $this->assertTrue($adapter->deleteDir(''));
    }

    public function testCreateFromFilesystem()
    {
        mkdir(__DIR__ . '/tmp');

        $adapter = MemoryAdapter::createFromFilesystem(new Filesystem(new Local(__DIR__)));
        $contents = $adapter->listContents('', true);
        $this->assertSame(2, count($contents));
        $this->assertSame('tmp', $contents[0]['path']);
        $this->assertSame('MemoryAdapterTest.php', $contents[1]['path']);

        $adapter = MemoryAdapter::createFromPath(__DIR__);
        $contents = $adapter->listContents('', true);
        $this->assertSame(2, count($contents));
        $this->assertSame('tmp', $contents[0]['path']);
        $this->assertSame('MemoryAdapterTest.php', $contents[1]['path']);

        rmdir(__DIR__ . '/tmp');
    }

    /**
     * @expectedException LogicException
     */
    public function testCreateFromFilesystemFail()
    {
        MemoryAdapter::createFromPath('does not exist');
    }
}

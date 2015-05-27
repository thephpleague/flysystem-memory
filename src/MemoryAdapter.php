<?php

namespace Twistor\Flysystem;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\Plugin\ListWith;
use League\Flysystem\Util;

/**
 * An adapter that keeps the filesystem in memory. Useful for tests.
 */
class MemoryAdapter implements AdapterInterface
{
    /**
     * The emulated filesystem.
     *
     * Start with the root directory initialized.
     *
     * @var array
     */
    protected $storage = ['' => ['type' => 'dir']];

    /**
     * Creates a Memory adapter from a filesystem folder.
     *
     * @param string $path The path to the folder.
     *
     * @return \Twistor\Flysystem\MemoryAdapter A new memory adapter.
     */
    public static function createFromPath($path)
    {
        if (!is_dir($path) || !is_readable($path)) {
            throw new \LogicException(sprintf('%s does not exist or is not readable.', $path));
        }

        return static::createFromFilesystem(new Filesystem(new Local($path)));
    }

    /**
     * Creates a Memory adapter from a Flysystem filesystem.
     *
     * @param \League\Flysystem\FilesystemInterface $filesystem The Flysystem filesystem.
     *
     * @return \Twistor\Flysystem\MemoryAdapter A new memory adapter.
     */
    public static function createFromFilesystem(FilesystemInterface $filesystem)
    {
        $filesystem->addPlugin(new ListWith());

        $adapter = new static();
        $config = new Config();

        foreach ($filesystem->listWith(['timestamp', 'visibility'], '', true) as $meta) {
            if ($meta['type'] === 'file') {
                $adapter->write($meta['path'], $filesystem->read($meta['path']), $config);
                $adapter->setVisibility($meta['path'], $meta['visibility']);
                $adapter->setTimestamp($meta['path'], $meta['timestamp']);

            } else {
                $adapter->createDir($meta['path'], $config);
            }
        }

        return $adapter;
    }

    /**
     * {@inheritdoc}
     */
    public function copy($path, $newpath)
    {
        // Make sure all the destination sub-directories exist.
        if (!$this->createDir(Util::dirname($newpath), new Config())) {
            return false;
        }

        $this->storage[$newpath] = $this->storage[$path];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function createDir($dirname, Config $config)
    {
        if ($this->hasFile($dirname)) {
            return false;
        }

        // Ensure sub-directories.
        if ($dirname !== '' && !$this->createDir(Util::dirname($dirname), $config)) {
            return false;
        }

        $this->storage[$dirname]['type'] = 'dir';

        return $this->getMetadata($dirname);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($path) {
        if (!$this->hasFile($path)) {
            return false;
        }

        unset($this->storage[$path]);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDir($dirname)
    {
        if (!$this->hasDirectory($dirname)) {
            return false;
        }

        // Empty the directory.
        foreach ($this->doListContents($dirname, true) as $path) {
            unset($this->storage[$path]);
        }
        unset($this->storage[$dirname]);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($path)
    {
        return $this->getKeys($path, ['type', 'path']);
    }

    /**
     * {@inheritdoc}
     */
    public function getMimetype($path)
    {
        return $this->getFileKeys($path, ['mimetype']);
    }

    /**
     * {@inheritdoc}
     */
    public function getSize($path)
    {
        return $this->getFileKeys($path, ['size']);
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp($path)
    {
        return $this->getFileKeys($path, ['timestamp']);
    }

    /**
     * {@inheritdoc}
     */
    public function getVisibility($path)
    {
        return $this->getFileKeys($path, ['visibility']);
    }

    /**
     * {@inheritdoc}
     */
    public function has($path)
    {
        return isset($this->storage[$path]);
    }

    /**
     * {@inheritdoc}
     */
    public function listContents($directory = '', $recursive = false)
    {
        $return = [];
        foreach ($this->doListContents($directory, $recursive) as $path) {
            // Filter out root.
            if ($path === '') {
                continue;
            }

            $return[] = $this->getMetadata($path);
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function read($path)
    {
        return $this->getFileKeys($path, ['path', 'contents']);
    }

    /**
     * {@inheritdoc}
     */
    public function readStream($path)
    {
        if (!$result = $this->read($path)) {
            return false;
        }

        $result['stream'] = fopen('php://temp', 'w+b');
        fwrite($result['stream'], $result['contents']);
        rewind($result['stream']);
        unset($result['contents']);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function rename($path, $newpath)
    {
        if (!$this->copy($path, $newpath)) {
            return false;
        }
        unset($this->storage[$path]);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function setVisibility($path, $visibility)
    {
        if (!$this->hasFile($path)) {
            return false;
        }

        $this->storage[$path]['visibility'] = $visibility;

        return $this->getVisibility($path);
    }

    /**
     * {@inheritdoc}
     */
    public function update($path, $contents, Config $config) {
        if (!$this->hasFile($path)) {
            return false;
        }

        $this->storage[$path]['contents'] = $contents;
        $this->storage[$path]['timestamp'] = time();
        $this->storage[$path]['size'] = strlen($contents);
        $this->storage[$path]['mimetype'] = Util::guessMimeType($path, $this->storage[$path]['contents']);

        if ($visibility = $config->get('visibility')) {
            $this->setVisibility($path, $visibility);
        }

        return $this->getMetadata($path);
    }

    /**
     * {@inheritdoc}
     */
    public function updateStream($path, $resource, Config $config)
    {
        return $this->update($path, stream_get_contents($resource), $config);
    }

    /**
     * {@inheritdoc}
     */
    public function write($path, $contents, Config $config)
    {
        if ($this->has($path)) {
            return false;
        }

        if (!$this->createDir(Util::dirname($path), $config)) {
            return false;
        }

        $this->storage[$path]['type'] = 'file';
        $this->storage[$path]['visibility'] = AdapterInterface::VISIBILITY_PUBLIC;

        return $this->update($path, $contents, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function writeStream($path, $resource, Config $config)
    {
        return $this->write($path, stream_get_contents($resource), $config);
    }

    /**
     * Filters the file system returning paths inside the directory.
     *
     *  @param string $directory
     *  @param bool   $recursive
     *
     * @return string[]
     */
    protected function doListContents($directory, $recursive)
    {
        $paths = array_keys($this->storage);

        if ($recursive) {
            return array_filter($paths, function ($path) use ($directory) {
                return $this->pathIsInDirectory($path, $directory);
            });
        }

        return array_filter($paths, function ($path) use ($directory) {
            return $this->pathIsInDirectory($path, $directory) && $this->noSubdir($path, $directory);
        });
    }

    /**
     * Returns the keys for a file.
     *
     * @param string $path
     * @param array  $keys
     *
     * @return array|false
     */
    protected function getFileKeys($path, array $keys)
    {
        if (!$this->hasFile($path)) {
            return false;
        }

        return $this->getKeys($path, $keys);
    }

    /**
     * Returns the keys for a path.
     *
     * @param string $path
     * @param array  $keys
     *
     * @return array|false
     */
    protected function getKeys($path, array $keys)
    {
        if (!$this->has($path)) {
            return false;
        }

        $return = [];
        foreach ($keys as $key) {
            if ($key === 'path') {
                $return[$key] = $path;
                continue;
            }

            $return[$key] = $this->storage[$path][$key];
        }

        return $return;
    }

    /**
     * Checks whether a directory exists.
     *
     * @param string $path The directory.
     *
     * @return bool True if it exists, and is a directory, false if not.
     */
    protected function hasDirectory($path)
    {
        return $this->has($path) && $this->storage[$path]['type'] === 'dir';
    }

    /**
     * Checks whether a file exists.
     *
     * @param string $path The file.
     *
     * @return bool True if it exists, and is a file, false if not.
     */
    protected function hasFile($path)
    {
        return $this->has($path) && $this->storage[$path]['type'] === 'file';
    }

    /**
     * Determines if the path is not inside a sub-directory.
     *
     * @param string $path
     * @param string $directory
     *
     * @return bool
     */
    protected function noSubdir($path, $directory)
    {
        return strpos(substr($path, strlen($directory) + 1), '/') === false;
    }

    /**
     * Determines if the path is inside the directory.
     *
     * @param string $path
     * @param string $directory
     *
     * @return bool
     */
    protected function pathIsInDirectory($path, $directory)
    {
        return $directory === '' || strpos($path, $directory . '/') === 0;
    }

    /**
     * Sets the timestamp of a file.
     *
     * @param string $path
     * @param int    $timestamp
     */
    protected function setTimestamp($path, $timestamp)
    {
        $this->storage[$path]['timestamp'] = (int) $timestamp;
    }
}

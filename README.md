# Flysystem memory adapter

[![Author](http://img.shields.io/badge/author-@chrisleppanen-blue.svg?style=flat-square)](https://twitter.com/chrisleppanen)
[![Build Status](https://img.shields.io/travis/twistor/flysystem-memory-adapter/master.svg?style=flat-square)](https://travis-ci.org/twistor/flysystem-memory-adapter)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/twistor/flysystem-memory-adapter.svg?style=flat-square)](https://scrutinizer-ci.com/g/twistor/flysystem-memory-adapter/code-structure)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/twistor/flysystem-memory-adapter.svg?style=flat-square)](https://packagist.org/packages/twistor/flysystem-memory-adapter)

This adapter keeps a filesystem in memory. It should be useful for tests,
allowing you to have a working filesystem without having to clean up after each
test run.

## Installation

```
composer require twistor/flysystem-memory-adapter
```

## Usage

```php
use League\Flysystem\Filesystem;
use Twistor\Flysystem\MemoryAdapter;

$filesystem = new Filesystem(new MemoryAdapter());

$filesystem->write('new_file.txt', 'yay a new text file!');

$contents = $filesystem->read('new_file.txt');

// If you have existing test files, you can populate the memory adapter from a
// filesystem path.
$adapter = MemoryAdapter::createFromPath('path/to/some/folder');

// Or, you can use an existing filesystem, and convert it.
$adapter = MemoryAdapter::createFromFilesystem($filesystem);
```

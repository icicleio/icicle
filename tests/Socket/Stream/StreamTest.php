<?php
namespace Icicle\Tests\Socket\Stream;

use Icicle\Loop;
use Icicle\Tests\TestCase;

abstract class StreamTest extends TestCase
{
    const CHUNK_SIZE = 8192;
    const TIMEOUT = 0.1;
    const WRITE_STRING = 'abcdefghijklmnopqrstuvwxyz';
    
    /**
     * @return \Icicle\Stream\StreamInterface[]
     */
    abstract public function createStreams();
    
    public function tearDown()
    {
        Loop\clear();
    }
}
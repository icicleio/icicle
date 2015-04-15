<?php
namespace Icicle\Tests\Stream;

use Icicle\Loop\Loop;
use Icicle\Stream\Stream;
use Icicle\Tests\TestCase;

class StreamTest extends TestCase
{
    use ReadableStreamTestTrait, WritableStreamTestTrait, WritableBufferedStreamTestTrait;
    
    const WRITE_STRING = 'abcdefghijklmnopqrstuvwxyz';
    const CHUNK_SIZE = 8192;
    const HWM = 16384;
    
    /**
     * @param   int|null $hwm
     *
     * @return  Stream[] Same stream instance for readable and writable.
     */
    public function createStreams($hwm = null)
    {
        $stream = new Stream($hwm);
        
        return [$stream, $stream];
    }
    
    public function tearDown()
    {
        Loop::clear();
    }
}
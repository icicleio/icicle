<?php
namespace Icicle\Tests\Stream;

use Icicle\Loop\Loop;
use Icicle\Stream\Stream;
use Icicle\Tests\TestCase;

class StreamTest extends TestCase
{
    use ReadableStreamTestTrait, WritableStreamTestTrait;
    
    const WRITE_STRING = 'abcdefghijklmnopqrstuvwxyz';
    
    /**
     * @return  [ReadableStreamInterface, WritableStreamInterface]
     */
    public function createStreams()
    {
        $stream = new Stream();
        
        return [$stream, $stream];
    }
    
    public function tearDown()
    {
        Loop::clear();
    }
}
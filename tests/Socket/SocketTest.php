<?php
namespace Icicle\Tests\Socket;

use Icicle\Socket\Socket;
use Icicle\Tests\TestCase;

class SocketTest extends TestCase
{
    protected $socket;
    
    public function setUp()
    {
        $this->socket = $this->getMockForAbstractClass(Socket::class, [fopen('php://memory', 'r+')]);
    }
    
    public function tearDown()
    {
        $this->socket = null; // Added so Socket::__destruct() is called while testing.
    }
    
    public function testIsOpen()
    {
        $this->assertTrue($this->socket->isOpen());
    }
    
    public function testGetResource()
    {
        $this->assertInternalType('resource', $this->socket->getResource());
    }
    
    /**
     * @expectedException \Icicle\Socket\Exception\InvalidArgumentError
     */
    public function testConstructWithNonResource()
    {
        $this->getMockForAbstractClass('Icicle\Socket\Socket', [1]);
    }
    
    /**
     * @depends testIsOpen
     */
    public function testClose()
    {
        $this->socket->close();
        
        $this->assertFalse($this->socket->isOpen());
        $this->assertFalse(is_resource($this->socket->getResource()));
    }
}

<?php
namespace Icicle\Tests\Socket;

use Icicle\Socket\Socket;
use Icicle\Tests\TestCase;

class SocketTest extends TestCase
{
    protected $socket;
    
    public function setUp()
    {
        $this->socket = $this->getMockForAbstractClass('Icicle\Socket\Socket', [fopen('php://memory', 'r+')]);
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
     * @expectedException Icicle\Socket\Exception\InvalidArgumentException
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
    
    public function testGetId()
    {
        $id = $this->socket->getId();
        $this->assertInternalType('integer', $id);
        $this->assertGreaterThan(0, $id);
    }
    
    /**
     * @depends testIsOpen
     */
    public function testIdAvailableAfterClose()
    {
        $id = $this->socket->getId();
        
        $this->socket->close();
        
        $this->assertFalse($this->socket->isOpen());
        $this->assertEquals($id, $this->socket->getId());
    }
    
    public function testParseSocketName()
    {
        $expectedAddress = '[::1]';
        $expectedPort = 8080;
        
        $uri = "tcp://{$expectedAddress}:{$expectedPort}";
        
        $server = @stream_socket_server($uri);
        
        if (!$server) {
            $this->markTestIncomplete('Could not create server socket.');
        }
        
        $client = @stream_socket_client($uri);
        
        if (!$client) {
            $this->markTestIncomplete('Could not create client socket.');
        }
        
        list($address, $port) = Socket::parseSocketName($client, true);
        
        $this->assertEquals($expectedAddress, $address);
        $this->assertEquals($expectedPort, $port);
        
        list($address, $port) = Socket::parseSocketName($client, false);
        
        $this->assertEquals($expectedAddress, $address);
        $this->assertInternalType('integer', $port);
        $this->assertGreaterThan(0, $port);
        
        list($address, $port) = Socket::parseSocketName($server, false);
        
        $this->assertEquals($expectedAddress, $address);
        $this->assertEquals($expectedPort, $port);
        
        fclose($server);
        fclose($client);
        
        $this->setExpectedException('Icicle\Socket\Exception\InvalidArgumentException');
        
        list($address, $port) = Socket::parseSocketName($this->socket, false);
    }
}

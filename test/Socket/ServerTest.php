<?php
namespace Icicle\Tests\Socket;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Socket\LocalClient;
use Icicle\Socket\Server;
use Icicle\Tests\TestCase;

class ServerTest extends TestCase
{
    const HOST_IPv4 = '127.0.0.1';
    const HOST_IPv6 = '[::1]';
    const PORT = 8080;
    
    public function tearDown()
    {
        Loop::clear();
    }
    
    public function testCreate()
    {
        $server = Server::create(self::HOST_IPv4, self::PORT);
        
        $this->assertSame(self::HOST_IPv4, $server->getAddress());
        $this->assertSame(self::PORT, $server->getPort());
    }
    
    public function testCreateIPv6()
    {
        $server = Server::create(self::HOST_IPv6, self::PORT);
        
        $this->assertSame(self::HOST_IPv6, $server->getAddress());
        $this->assertSame(self::PORT, $server->getPort());
    }
    
    /**
     * @depends testCreate
     * @expectedException Icicle\Socket\Exception\FailureException
     */
    public function testCreateInvalidHost()
    {
        $server = Server::create('invalid.host', self::PORT);
    }
    
    /**
     * @depends testCreate
     */
    public function testAccept()
    {
        $server = Server::create(self::HOST_IPv4, self::PORT);
        
        $promise = $server->accept();
        
        $client = LocalClient::connect(self::HOST_IPv4, self::PORT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\RemoteClient'));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testAccept
     */
    public function testAcceptAfterClose()
    {
        $server = Server::create(self::HOST_IPv4, self::PORT);
        
        $server->close();
        
        $promise = $server->accept();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\ClosedException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testAccept
     */
    public function testAcceptThenClose()
    {
        $server = Server::create(self::HOST_IPv4, self::PORT);
        
        $promise = $server->accept();
        
        $server->close();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\ClosedException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    
    /**
     * @depends testAccept
     */
    public function testCancelAccept()
    {
        $exception = new Exception();
        
        $server = Server::create(self::HOST_IPv4, self::PORT);
        
        $promise = $server->accept();
        
        $promise->cancel($exception);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
        
        $server->close();
    }
    
    /**
     * @depends testAccept
     */
    public function testAcceptOnClosedSocket()
    {
        $server = Server::create(self::HOST_IPv4, self::PORT);
        
        fclose($server->getResource());
        
        $promise = $server->accept();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\ClosedException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::tick();
        
        $server->close();
    }
    
    /**
     * @depends testAccept
     */
    public function testSimultaneousAccept()
    {
        $server = Server::create(self::HOST_IPv4, self::PORT);
        
        $promise1 = $server->accept();
        
        $promise2 = $server->accept();
        
        $client = LocalClient::connect(self::HOST_IPv4, self::PORT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\RemoteClient'));
        
        $promise1->done($callback, $this->createCallback(0));
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\UnavailableException'));
        
        $promise2->done($this->createCallback(0), $callback);
        
        Loop::run();
        
        $server->close();
    }
    
    /**
     * @depends testCreate
     */
    public function testAcceptOnClosedClient()
    {
        $server = Server::create(self::HOST_IPv4, self::PORT);
        
        $promise = $server->accept();
        
        $socket = @stream_socket_client('tcp://' . self::HOST_IPv4 . ':' . self::PORT, $errno, $errstr, 1, STREAM_CLIENT_CONNECT);
        
        if (!$socket || $errno) {
            $this->fail("Could not create client socket. [Errno {$errno}] {$errstr}");
        }
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\RemoteClient'));
        
        $promise->done($callback, $this->createCallback(0));
        
        fclose($socket);
        
        Loop::run();
        
        $server->close();
    }
    
    /**
     * @require extension openssl
     * @depends testCreate
     */
/*
    public function testCreateWithPem()
    {
        $path = tempnam(sys_get_temp_dir(), 'Icicle');
        $passphrase = 'icicle';
        
        Server::generateCert(
            'US',
            'MN',
            'Minneapolis',
            'Icicle',
            'Security',
            'localhost',
            'hello@icicle.io',
            $passphrase,
            $path
        );
        
        $server = Server::create('localhost', self::PORT, ['pem' => $path, 'passphrase' => $passphrase]);
        
        $this->assertTrue($server->isSecure());
        
        $promise = $server->accept();
        
        $client = LocalClient::connect('localhost', self::PORT, true);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\RemoteClient'));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
*/
    
    /**
     * @depends testCreateWithPem
     * @expectedException Icicle\Socket\Exception\InvalidArgumentException
     */
/*
    public function testCreateWithInvalidPem()
    {
        $server = Server::create(self::HOST_IPv4, self::PORT, ['pem' => 'invalid/pem.pem']);
    }
*/
}

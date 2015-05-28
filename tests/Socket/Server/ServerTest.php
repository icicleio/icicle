<?php
namespace Icicle\Tests\Socket\Server;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Socket\Server\Server;
use Icicle\Tests\TestCase;

class ServerTest extends TestCase
{
    const HOST_IPv4 = '127.0.0.1';
    const PORT = 51337;
    const TIMEOUT = 0.1;
    const CONNECT_TIMEOUT = 1;

    /**
     * @var \Icicle\Socket\Server\Server|null
     */
    protected $server;
    
    public function tearDown()
    {
        Loop::clear();
        
        if ($this->server instanceof Server) {
            $this->server->close();
        }
    }
    
    public function createServer()
    {
        $host = self::HOST_IPv4;
        $port = self::PORT;
        
        $context = [];
        
        $context['socket'] = [];
        $context['socket']['bindto'] = "{$host}:{$port}";
        
        $context = stream_context_create($context);
        
        $uri = sprintf('tcp://%s:%d', $host, $port);
        $socket = @stream_socket_server($uri, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        
        if (!$socket || $errno) {
            $this->fail("Could not create server {$host}:{$port}: [Errno: {$errno}] {$errstr}");
        }
        
        return new Server($socket);
    }
    
    public function testInvalidSocketType()
    {
        $this->server = new Server(fopen('php://memory', 'r+'));
        
        $this->assertFalse($this->server->isOpen());
    }
    
    public function testAccept()
    {
        $this->server = $this->createServer();
        
        $promise = $this->server->accept();
        
        $client = stream_socket_client(
            'tcp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT
        );
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Client\ClientInterface'));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();

        fclose($client);
    }

    /**
     * @depends testAccept
     */
    public function testAcceptAfterClose()
    {
        $this->server = $this->createServer();
        
        $this->server->close();
        
        $promise = $this->server->accept();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\UnavailableException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testAccept
     */
    public function testAcceptThenClose()
    {
        $this->server = $this->createServer();
        
        $promise = $this->server->accept();
        
        $this->server->close();
        
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
        
        $this->server = $this->createServer();
        
        $promise = $this->server->accept();
        
        $promise->cancel($exception);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }

    /**
     * @depends testAccept
     */
    public function testSimultaneousAccept()
    {
        $this->server = $this->createServer();
        
        $promise1 = $this->server->accept();
        
        $promise2 = $this->server->accept();
        
        $client = stream_socket_client(
            'tcp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Client\ClientInterface'));
        
        $promise1->done($callback, $this->createCallback(0));
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\BusyException'));
        
        $promise2->done($this->createCallback(0), $callback);
        
        Loop::run();

        fclose($client);
    }
    
    /**
     * @depends testAccept
     */
    public function testAcceptOnClosedClient()
    {
        $this->server = $this->createServer();
        
        $promise = $this->server->accept();
        
        $client = stream_socket_client(
            'tcp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        if (!$client || $errno) {
            $this->fail("Could not create client socket. [Errno {$errno}] {$errstr}");
        }

        fclose($client);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Client\ClientInterface'));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
}

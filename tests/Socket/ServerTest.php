<?php
namespace Icicle\Tests\Socket;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Socket\Server;
use Icicle\Tests\TestCase;

class ServerTest extends TestCase
{
    const HOST_IPv4 = '127.0.0.1';
    const HOST_IPv6 = '[::1]';
    const PORT = 51337;
    const TIMEOUT = 0.1;
    const CONNECT_TIMEOUT = 1;
    const CERT_HEADER = '-----BEGIN CERTIFICATE-----';
    
    protected $server;
    
    public function tearDown()
    {
        Loop::clear();
        
        if ($this->server instanceof Server) {
            $this->server->close();
        }
    }
    
    public function testCreate()
    {
        $this->server = Server::create(self::HOST_IPv4, self::PORT);
        
        $this->assertSame(self::HOST_IPv4, $this->server->getAddress());
        $this->assertSame(self::PORT, $this->server->getPort());
        
        $this->server->close();
    }
    
    public function testCreateIPv6()
    {
        $this->server = Server::create(self::HOST_IPv6, self::PORT);
        
        $this->assertSame(self::HOST_IPv6, $this->server->getAddress());
        $this->assertSame(self::PORT, $this->server->getPort());
    }
    
    /**
     * @medium
     * @depends testCreate
     * @expectedException Icicle\Socket\Exception\FailureException
     */
    public function testCreateInvalidHost()
    {
        $this->server = Server::create('invalid.host', self::PORT);
        
        $this->server->close();
    }
    
    public function testInvalidSocketType()
    {
        $this->server = new Server(fopen('php://memory', 'r+'));
        
        $this->assertFalse($this->server->isOpen());
    }
    
    /**
     * @depends testCreate
     */
    public function testAccept()
    {
        $this->server = Server::create(self::HOST_IPv4, self::PORT);
        
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
                 ->with($this->isInstanceOf('Icicle\Socket\ClientInterface'));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testCreate
     */
    public function testAcceptWithTimeout()
    {
        $this->server = Server::create(self::HOST_IPv4, self::PORT);
        
        $promise = $this->server->accept(self::TIMEOUT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }

    
    /**
     * @depends testAccept
     */
    public function testAcceptAfterClose()
    {
        $this->server = Server::create(self::HOST_IPv4, self::PORT);
        
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
        $this->server = Server::create(self::HOST_IPv4, self::PORT);
        
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
        
        $this->server = Server::create(self::HOST_IPv4, self::PORT);
        
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
    public function testAcceptOnClosedSocket()
    {
        $this->server = Server::create(self::HOST_IPv4, self::PORT);
        
        fclose($this->server->getResource());
        
        $promise = $this->server->accept();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\UnavailableException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::tick();
    }
    
    /**
     * @depends testAccept
     */
    public function testSimultaneousAccept()
    {
        $this->server = Server::create(self::HOST_IPv4, self::PORT);
        
        $promise1 = $this->server->accept();
        
        $promise2 = $this->server->accept();
        
        $client = stream_socket_client(
            'tcp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT
        );
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\ClientInterface'));
        
        $promise1->done($callback, $this->createCallback(0));
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\BusyException'));
        
        $promise2->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testCreate
     */
    public function testAcceptOnClosedClient()
    {
        $this->server = Server::create(self::HOST_IPv4, self::PORT);
        
        $promise = $this->server->accept();
        
        $client = stream_socket_client(
            'tcp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT
        );
        
        if (!$client || $errno) {
            $this->fail("Could not create client socket. [Errno {$errno}] {$errstr}");
        }
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\ClientInterface'));
        
        $promise->done($callback, $this->createCallback(0));
        
        fclose($client);
        
        Loop::run();
    }
    
    /**
     * @medium
     * @require extension openssl
     */
    public function testGenerateCertToString()
    {
        $cert = Server::generateCert(
            'US',
            'MN',
            'Minneapolis',
            'Icicle',
            'Security',
            'localhost',
            'hello@icicle.io'
        );
        
        $this->assertSame(self::CERT_HEADER, substr($cert, 0, strlen(self::CERT_HEADER)));
        
        $cert = Server::generateCert(
            'US',
            'MN',
            'Minneapolis',
            'Icicle',
            'Security',
            'localhost',
            'hello@icicle.io',
            'icicle'
        );
        
        $this->assertSame(self::CERT_HEADER, substr($cert, 0, strlen(self::CERT_HEADER)));
    }
    
    /**
     * @medium
     * @require extension openssl
     */
    public function testGenerateCertToFile()
    {
        $path = tempnam(sys_get_temp_dir(), 'Icicle');
        
        $cert = Server::generateCert(
            'US',
            'MN',
            'Minneapolis',
            'Icicle',
            'Security',
            'localhost',
            'hello@icicle.io',
            null,
            $path
        );
        
        $this->assertGreaterThan(0, $cert);
        
        $contents = file_get_contents($path);
        
        $this->assertSame(self::CERT_HEADER, substr($contents, 0, strlen(self::CERT_HEADER)));
        
        unlink($path);
        
        $path = tempnam(sys_get_temp_dir(), 'Icicle');
        
        $cert = Server::generateCert(
            'US',
            'MN',
            'Minneapolis',
            'Icicle',
            'Security',
            'localhost',
            'hello@icicle.io',
            'icicle',
            $path
        );
        
        $this->assertGreaterThan(0, $cert);
        
        $contents = file_get_contents($path);
        
        $this->assertSame(self::CERT_HEADER, substr($contents, 0, strlen(self::CERT_HEADER)));
        
        unlink($path);
    }
    
    /**
     * @medium
     * @require extension openssl
     * @depends testCreate
     * @depends testGenerateCertToFile
     */
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
        
        $this->server = Server::create(self::HOST_IPv4, self::PORT, ['pem' => $path, 'passphrase' => $passphrase]);
        
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
                 ->with($this->isInstanceOf('Icicle\Socket\ClientInterface'));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
        
        unlink($path);
    }
    
    /**
     * @expectedException Icicle\Socket\Exception\InvalidArgumentException
     */
    public function testCreateWithInvalidPemPath()
    {
        $this->server = Server::create(self::HOST_IPv4, self::PORT, ['pem' => 'invalid/pem.pem']);
    }
}

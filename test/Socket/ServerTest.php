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

    public function tearDown()
    {
        Loop::clear();
    }
    
    public function testCreate()
    {
        $server = Server::create(self::HOST_IPv4, self::PORT);
        
        $this->assertSame(self::HOST_IPv4, $server->getAddress());
        $this->assertSame(self::PORT, $server->getPort());
        
        $server->close();
    }
    
    public function testCreateIPv6()
    {
        $server = Server::create(self::HOST_IPv6, self::PORT);
        
        $this->assertSame(self::HOST_IPv6, $server->getAddress());
        $this->assertSame(self::PORT, $server->getPort());
        
        $server->close();
    }
    
    /**
     * @medium
     * @depends testCreate
     * @expectedException Icicle\Socket\Exception\FailureException
     */
    public function testCreateInvalidHost()
    {
        $server = Server::create('invalid.host', self::PORT);
        
        $server->close();
    }
    
    public function testWithInvalidSocketType()
    {
        $server = new Server(fopen('php://memory', 'r+'));
        
        $this->assertFalse($server->isOpen());
    }
    
    /**
     * @depends testCreate
     */
    public function testAccept()
    {
        $server = Server::create(self::HOST_IPv4, self::PORT);
        
        $promise = $server->accept();
        
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
        
        $server->close();
    }
    
    /**
     * @depends testCreate
     */
    public function testAcceptWithTimeout()
    {
        $server = Server::create(self::HOST_IPv4, self::PORT);
        
        $promise = $server->accept(self::TIMEOUT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
        
        $server->close();
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
/*
    public function testAcceptAfterEof()
    {
        $server = Server::create(self::HOST_IPv4, self::PORT);
        
        $promise = $server->accept();
        
        fclose($server->getResource());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\ClosedException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        $client = stream_socket_client(
            'tcp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT
        );
        
        Loop::run();
        
        $server->close();
    }
*/
    
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
        
        $client = stream_socket_client(
            'tcp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT
        );
        
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
        
        $server->close();
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
        
        $server = Server::create(self::HOST_IPv4, self::PORT, ['pem' => $path, 'passphrase' => $passphrase]);
        
        $promise = $server->accept();
        
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
        
        $server->close();
    }
    
    /**
     * @expectedException Icicle\Socket\Exception\InvalidArgumentException
     */
    public function testCreateWithInvalidPemPath()
    {
        $server = Server::create(self::HOST_IPv4, self::PORT, ['pem' => 'invalid/pem.pem']);
    }
}

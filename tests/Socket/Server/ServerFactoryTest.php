<?php
namespace Icicle\Tests\Socket\Server;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Socket\Server\Server;
use Icicle\Socket\Server\ServerFactory;
use Icicle\Tests\TestCase;

class ServerFactoryTest extends TestCase
{
    const HOST_IPv4 = '127.0.0.1';
    const HOST_IPv6 = '[::1]';
    const PORT = 51337;
    const TIMEOUT = 0.1;
    const CONNECT_TIMEOUT = 1;
    const CERT_HEADER = '-----BEGIN CERTIFICATE-----';
    
    protected $factory;
    
    protected $server;
    
    public function setUp()
    {
        $this->factory = new ServerFactory();
    }
    
    public function tearDown()
    {
        Loop::clear();
        
        if ($this->server instanceof Server) {
            $this->server->close();
        }
    }
    
    public function testCreate()
    {
        $this->server = $this->factory->create(self::HOST_IPv4, self::PORT);
        
        $this->assertInstanceOf('Icicle\Socket\Server\ServerInterface', $this->server);
        
        $this->assertSame(self::HOST_IPv4, $this->server->getAddress());
        $this->assertSame(self::PORT, $this->server->getPort());
        
        $this->server->close();
    }
    
    public function testCreateIPv6()
    {
        $this->server = $this->factory->create(self::HOST_IPv6, self::PORT);
        
        $this->assertInstanceOf('Icicle\Socket\Server\ServerInterface', $this->server);
        
        $this->assertSame(self::HOST_IPv6, $this->server->getAddress());
        $this->assertSame(self::PORT, $this->server->getPort());
    }
    
    /**
     * @medium
     * @depends testCreate
     * @expectedException \Icicle\Socket\Exception\FailureException
     */
    public function testCreateInvalidHost()
    {
        $this->server = $this->factory->create('invalid.host', self::PORT);
        
        $this->server->close();
    }
    
    /**
     * @medium
     * @require extension openssl
     * @depends testCreate
     */
    public function testCreateWithPem()
    {
        $path = tempnam(sys_get_temp_dir(), 'Icicle');
        $passphrase = 'icicle';

        /** @var callable $generateCert */

        $generateCert = require dirname(dirname(__DIR__)) . '/generate-cert.php';

        $generateCert(
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

        $this->server = $this->factory->create(self::HOST_IPv4, self::PORT, ['pem' => $path, 'passphrase' => $passphrase]);
        
        $this->assertInstanceOf('Icicle\Socket\Server\ServerInterface', $this->server);
        
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
        
        unlink($path);
    }
    
    /**
     * @expectedException \Icicle\Socket\Exception\InvalidArgumentException
     */
    public function testCreateWithInvalidPemPath()
    {
        $this->server = $this->factory->create(self::HOST_IPv4, self::PORT, ['pem' => 'invalid/pem.pem']);
    }
}

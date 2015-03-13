<?php
namespace Icicle\Tests\Socket;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Socket\Client;
use Icicle\Tests\TestCase;

class ClientTest extends TestCase
{
    const HOST_IPv4 = '127.0.0.1';
    const HOST_IPv6 = '[::1]';
    const PORT = 51337;
    const TIMEOUT = 0.1;
    const CONNECT_TIMEOUT = 1;
    const CERT_HEADER = '-----BEGIN CERTIFICATE-----';
    
    public function createServer()
    {
        $host = self::HOST_IPv4;
        $port = self::PORT;
        
        $context = [];
        
        $context['socket'] = [];
        $context['socket']['bindto'] = "{$host}:{$port}";
        
        $context = stream_context_create($context);
        
        $socket = stream_socket_server(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context
        );
        
        if (!$socket || $errno) {
            $this->fail("Could not create server {$host}:{$port}: [Errno: {$errno}] {$errstr}");
        }
        
        return $socket;
    }
    
    public function createServerIPv6()
    {
        $host = self::HOST_IPv6;
        $port = self::PORT;
        
        $context = [];
        
        $context['socket'] = [];
        $context['socket']['bindto'] = "{$host}:{$port}";
        
        $context = stream_context_create($context);
        
        $socket = stream_socket_server(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context
        );
        
        if (!$socket || $errno) {
            $this->fail("Could not create server {$host}:{$port}: [Errno: {$errno}] {$errstr}");
        }
        
        return $socket;
    }
    
    public function createSecureServer($path)
    {
        $host = self::HOST_IPv4;
        $port = self::PORT;
        
        $dn = [
            'countryName' => 'US',
            'stateOrProvinceName' => 'MN',
            'localityName' => 'Minneapolis',
            'organizationName' => 'Icicle',
            'organizationalUnitName' => 'Security',
            'commonName' => 'localhost',
            'emailAddress' => 'hello@icicle.io'
        ];
        
        $privkey = openssl_pkey_new(['private_key_bits' => 2048]);
        $cert = openssl_csr_new($dn, $privkey);
        $cert = openssl_csr_sign($cert, null, $privkey, 365);
        
        openssl_x509_export($cert, $cert);
        openssl_pkey_export($privkey, $privkey);
        
        $pem = $cert . $privkey;
        
        file_put_contents($path, $pem);
        
        $context = [];
        
        $context['socket'] = [];
        $context['socket']['bindto'] = "{$host}:{$port}";
        
        $context['ssl'] = [];
        $context['ssl']['local_cert'] = $path;
        $context['ssl']['disable_compression'] = true;
        
        $context = stream_context_create($context);
        
        $socket = stream_socket_server(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context
        );
        
        if (!$socket || $errno) {
            $this->fail("Could not create server {$host}:{$port}: [Errno: {$errno}] {$errstr}");
        }
        
        return $socket;
    }
    
    public function tearDown()
    {
        Loop::clear();
    }
    
    public function testConnect()
    {
        $server = $this->createServer();
        
        $promise = Client::connect(self::HOST_IPv4, self::PORT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Client'));
        
        $promise->done($callback, $this->createCallback(0));
        
        $promise->done(function (Client $client) {
            $this->assertSame($client->getLocalAddress(), self::HOST_IPv4);
            $this->assertSame($client->getRemoteAddress(), self::HOST_IPv4);
            $this->assertInternalType('integer', $client->getLocalPort());
            $this->assertSame($client->getRemotePort(), self::PORT);
        });
        
        Loop::run();
        
        fclose($server);
    }
    
    /**
     * @depends testConnect
     */
    public function testConnectIPv6()
    {
        $server = $this->createServerIPv6();
        
        $promise = Client::connect(self::HOST_IPv6, self::PORT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Client'));
        
        $promise->done($callback, $this->createCallback(0));
        
        $promise->done(function (Client $client) {
            $this->assertSame($client->getLocalAddress(), self::HOST_IPv6);
            $this->assertSame($client->getRemoteAddress(), self::HOST_IPv6);
            $this->assertInternalType('integer', $client->getLocalPort());
            $this->assertSame($client->getRemotePort(), self::PORT);
        });
        
        Loop::run();
        
        fclose($server);
    }
    
    /**
     * @medium
     * @depends testConnect
     */
    public function testConnectFailure()
    {
        $promise = Client::connect('invalid.host', self::PORT, ['timeout' => 1]);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\FailureException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @medium
     * @depends testConnect
     */
    public function testConnectTimeout()
    {
        $promise = Client::connect('8.8.8.8', 80, ['timeout' => 1]);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    public function testInvalidSocketType()
    {
        $client = new Client(fopen('php://memory', 'r+'));
        
        $this->assertFalse($client->isOpen());
    }
    
    /**
     * @medium
     * @depends testConnect
     * @require extension openssl
     */
    public function testEnableCrypto()
    {
        $path = tempnam(sys_get_temp_dir(), 'Icicle');
        
        $server = $this->createSecureServer($path);
        
        $promise = Client::connect(self::HOST_IPv4, self::PORT, ['cn' => 'localhost', 'allow_self_signed' => true]);
        
        $promise = $promise
            ->tap(function () use ($server) {
                $socket = stream_socket_accept($server);
                $socket = new Client($socket);
                $socket->enableCrypto(STREAM_CRYPTO_METHOD_TLS_SERVER, self::TIMEOUT);
            })
            ->then(function (Client $client) {
                return $client->enableCrypto(STREAM_CRYPTO_METHOD_TLS_CLIENT, self::TIMEOUT);
            })
            ->tap(function (Client $client) {
                $this->assertTrue($client->isCryptoEnabled());
            });
        
        $promise->done($this->createCallback(1));
        
        Loop::run();
        
        fclose($server);
        unlink($path);
    }
    
    /**
     * @medium
     * @depends testConnect
     * @require extension openssl
     */
    public function testEnableCryptoFailure()
    {
        $path = tempnam(sys_get_temp_dir(), 'Icicle');
        
        $server = $this->createSecureServer($path);
        
        $promise = Client::connect(self::HOST_IPv4, self::PORT);
        
        $promise = $promise
            ->tap(function () use ($server) {
                $socket = stream_socket_accept($server);
                $socket = new Client($socket);
                $socket->enableCrypto(STREAM_CRYPTO_METHOD_TLS_SERVER, self::TIMEOUT);
            })
            ->then(function (Client $client) {
                return $client->enableCrypto(STREAM_CRYPTO_METHOD_SSLv3_CLIENT, self::TIMEOUT);
            });
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\FailureException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
        
        fclose($server);
        unlink($path);
    }
}

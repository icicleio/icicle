<?php
namespace Icicle\Tests\Socket\Client;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Promise;
use Icicle\Socket\Client\Client;
use Icicle\Tests\TestCase;

class ClientTest extends TestCase
{
    const HOST_IPv4 = '127.0.0.1';
    const PORT = 51337;
    const TIMEOUT = 0.1;
    const CONNECT_TIMEOUT = 1;
    const CERT_HEADER = '-----BEGIN CERTIFICATE-----';
    
    public function createClient()
    {
        $host = self::HOST_IPv4;
        $port = self::PORT;
        
        $context = [];
        
        $context['socket'] = [];
        $context['socket']['connect'] = "{$host}:{$port}";
        
        $context['ssl'] = [];
        $context['ssl']['capture_peer_cert'] = true;
        $context['ssl']['capture_peer_chain'] = true;
        $context['ssl']['capture_peer_cert_chain'] = true;
        
        $context['ssl']['verify_peer'] = true;
        $context['ssl']['allow_self_signed'] = true;
        $context['ssl']['verify_depth'] = 10;
        
        $context['ssl']['CN_match'] = 'localhost';
        $context['ssl']['peer_name'] = 'localhost';
        $context['ssl']['disable_compression'] = true;
        
        $context = stream_context_create($context);
        
        $uri = sprintf('tcp://%s:%d', $host, $port);
        $socket = @stream_socket_client($uri, $errno, $errstr, null, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT, $context);
        
        if (!$socket || $errno) {
            $this->fail("Could not connect to {$uri}; Errno: {$errno}; {$errstr}");
        }
        
        return new Promise(function ($resolve, $reject) use ($socket) {
            $await = Loop::await($socket, function ($resource, $expired) use (&$await, $resolve, $reject) {
                $await->free();
                $resolve(new Client($resource));
            });
            
            $await->listen();
        });
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
    
    public function testInvalidSocketType()
    {
        $client = new Client(fopen('php://memory', 'r+'));
        
        $this->assertFalse($client->isOpen());
    }
    
    /**
     * @medium
     * @require extension openssl
     */
    public function testEnableCrypto()
    {
        $path = tempnam(sys_get_temp_dir(), 'Icicle');
        
        $server = $this->createSecureServer($path);
        
        $promise = $this->createClient();
        
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
     * @require extension openssl
     */
    public function testEnableCryptoFailure()
    {
        $path = tempnam(sys_get_temp_dir(), 'Icicle');
        
        $server = $this->createSecureServer($path);
        
        $promise = $this->createClient();
        
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

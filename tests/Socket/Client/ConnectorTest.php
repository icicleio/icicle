<?php
namespace Icicle\Tests\Socket\Client;

use Icicle\Loop;
use Icicle\Socket\Client\Client;
use Icicle\Socket\Client\Connector;
use Icicle\Tests\TestCase;

class ConnectorTest extends TestCase
{
    const HOST_IPv4 = '127.0.0.1';
    const HOST_IPv6 = '[::1]';
    const HOST_UNIX = '/tmp/icicle-tmp.sock';
    const PORT = 51337;
    const TIMEOUT = 1;

    /**
     * @var \Icicle\Socket\Client\Connector
     */
    protected $connector;
    
    public function createServer()
    {
        $host = self::HOST_IPv4;
        $port = self::PORT;
        
        $context = [];
        
        $context['socket'] = [];
        $context['socket']['bindto'] = "{$host}:{$port}";
        
        $context = stream_context_create($context);
        
        $socket = stream_socket_server(
            sprintf('tcp://%s:%d', $host, $port),
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context
        );
        
        if (!$socket || $errno) {
            $this->fail(sprintf('Could not create server %s:%d: [Errno: %d] %s', $host, $port, $errno, $errstr));
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
            sprintf('tcp://%s:%d', $host, $port),
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context
        );
        
        if (!$socket || $errno) {
            $this->fail(sprintf('Could not create server %s:%d: [Errno: %d] %s', $host, $port, $errno, $errstr));
        }
        
        return $socket;
    }

    public function createServerUnix($path)
    {
        $context = [];

        $context['socket'] = [];
        $context['socket']['bindto'] = $path;

        $context = stream_context_create($context);

        $socket = stream_socket_server(
            sprintf('unix://%s', $path),
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $context
        );

        if (!$socket || $errno) {
            $this->fail(sprintf('Could not create server %s: [Errno: %d] %s', $path, $errno, $errstr));
        }

        return $socket;
    }

    public function createSecureServer($path)
    {
        $host = self::HOST_IPv4;
        $port = self::PORT;

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
            null,
            $path
        );

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
    
    public function setUp()
    {
        $this->connector = new Connector();
    }
    
    public function tearDown()
    {
        Loop\clear();
    }
    
    public function testConnect()
    {
        $server = $this->createServer();
        
        $promise = $this->connector->connect(self::HOST_IPv4, self::PORT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Client\ClientInterface'));
        
        $promise->done($callback, $this->createCallback(0));
        
        $promise->done(function (Client $client) {
            $this->assertTrue($client->isOpen());
            $this->assertSame($client->getLocalAddress(), self::HOST_IPv4);
            $this->assertSame($client->getRemoteAddress(), self::HOST_IPv4);
            $this->assertInternalType('integer', $client->getLocalPort());
            $this->assertSame($client->getRemotePort(), self::PORT);
        });
        
        Loop\run();
        
        fclose($server);
    }
    
    /**
     * @depends testConnect
     */
    public function testConnectIPv6()
    {
        $server = $this->createServerIPv6();
        
        $promise = $this->connector->connect(self::HOST_IPv6, self::PORT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Client\ClientInterface'));
        
        $promise->done($callback, $this->createCallback(0));
        
        $promise->done(function (Client $client) {
            $this->assertTrue($client->isOpen());
            $this->assertSame($client->getLocalAddress(), self::HOST_IPv6);
            $this->assertSame($client->getRemoteAddress(), self::HOST_IPv6);
            $this->assertInternalType('integer', $client->getLocalPort());
            $this->assertSame($client->getRemotePort(), self::PORT);
        });
        
        Loop\run();
        
        fclose($server);
    }

    /**
     * @depends testConnect
     */
    public function testConnectUnix()
    {
        $server = $this->createServerUnix(self::HOST_UNIX);

        $promise = $this->connector->connect(self::HOST_UNIX, null, ['protocol' => 'unix']);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Socket\Client\ClientInterface'));

        $promise->done($callback, $this->createCallback(0));

        $promise->done(function (Client $client) {
            $this->assertTrue($client->isOpen());
            $this->assertSame(self::HOST_UNIX, $client->getRemoteAddress());
            $this->assertSame(null, $client->getRemotePort());
        });

        Loop\run();

        fclose($server);
        unlink(self::HOST_UNIX);
    }
    
    /**
     * @medium
     * @depends testConnect
     */
    public function testConnectFailure()
    {
        $promise = $this->connector->connect('invalid.host', self::PORT, ['timeout' => 1]);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\FailureException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    /**
     * @medium
     * @depends testConnect
     */
    public function testConnectTimeout()
    {
        $promise = $this->connector->connect('8.8.8.8', 8080, ['timeout' => 1]);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();
    }

    /**
     * @medium
     * @depends testConnect
     */
    public function testConnectWithCAFile()
    {
        $path = tempnam(sys_get_temp_dir(), 'Icicle');

        $server = $this->createServer();

        $promise = $this->connector->connect(self::HOST_IPv4, self::PORT, ['cafile' => $path]);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Socket\Client\ClientInterface'));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();

        fclose($server);

        unlink($path);
    }

    public function testInvalidCAFile()
    {
        $path = '/invalid/path/to/cafile.pem';

        $server = $this->createServer();

        $promise = $this->connector->connect(self::HOST_IPv4, self::PORT, ['cafile' => $path]);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\InvalidArgumentException'));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();

        fclose($server);
    }

    /**
     * @medium
     * @requires extension openssl
     * @depends testConnect
     */
    public function testSecureConnect()
    {
        $path = tempnam(sys_get_temp_dir(), 'Icicle');

        $server = $this->createSecureServer($path);

        $promise = $this->connector->connect(
            self::HOST_IPv4,
            self::PORT,
            ['name' => 'localhost', 'cn' => 'localhost', 'allow_self_signed' => true]
        );

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Socket\Client\ClientInterface'));

        $promise->done($callback, $this->createCallback(0));

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

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Socket\Client\ClientInterface'));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();

        fclose($server);
        unlink($path);
    }

    /**
     * @medium
     * @depends testSecureConnect
     */
    public function testSecureConnectNameMismatch()
    {
        $path = tempnam(sys_get_temp_dir(), 'Icicle');

        $server = $this->createSecureServer($path);

        $promise = $this->connector->connect(
            self::HOST_IPv4,
            self::PORT,
            ['name' => 'icicle.io', 'cn' => 'icicle.io', 'allow_self_signed' => true]
        );

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Socket\Client\ClientInterface'));

        $promise->done($callback, $this->createCallback(0));

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

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Socket\Exception\FailureException'));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();

        fclose($server);
        unlink($path);
    }

    /**
     * @medium
     * @depends testSecureConnect
     */
    public function testSecureConnectNoSelfSigned()
    {
        $path = tempnam(sys_get_temp_dir(), 'Icicle');

        $server = $this->createSecureServer($path);

        $promise = $this->connector->connect(
            self::HOST_IPv4,
            self::PORT,
            ['name' => 'localhost', 'cn' => 'localhost', 'allow_self_signed' => false]
        );

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Socket\Client\ClientInterface'));

        $promise->done($callback, $this->createCallback(0));

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

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Socket\Exception\FailureException'));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();

        fclose($server);
        unlink($path);
    }
}

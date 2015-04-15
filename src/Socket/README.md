# Sockets

The socket component implements network sockets as promise-based streams, server, and datagram. Creating a server and accepting connections is very simple, requiring only a few lines of code.

The example below implements HTTP server that responds to any request with `Hello world!` implemented using the promise-based server and client provided by the Socket component.

```php
use Icicle\Loop\Loop;
use Icicle\Socket\Client\ClientInterface;
use Icicle\Socket\Server\ServerFactory;

$server = (new ServerFactory())->create('localhost', 60000);

$handler = function (ClientInterface $client) use (&$handler, &$error, $server) {
    $server->accept()->done($handler, $error);
    
    $response  = "HTTP/1.1 200 OK\r\n";
    $response .= "Content-Length: 12\r\n";
    $response .= "Connection: close\r\n";
    $response .= "\r\n";
    $response .= "Hello world!";
    
    $client->end($response);
};

$error = function (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
};

$server->accept()->done($handler, $error);

echo "Server listening on {$server->getAddress()}:{$server->getPort()}\n";

Loop::run();
```

The example below shows the same HTTP server as above, instead implemented using a coroutine (see the [Coroutine API documentation](../Coroutine)).

```php
use Icicle\Coroutine\Coroutine;
use Icicle\Loop\Loop;
use Icicle\Socket\Client\ClientInterface;
use Icicle\Socket\Server\ServerInterface;
use Icicle\Socket\Server\ServerFactory;

$server = (new ServerFactory())->create('localhost', 60000);

$generator = function (ServerInterface $server) {
    echo "Server listening on {$server->getAddress()}:{$server->getPort()}\n";
    
    $generator = function (ClientInterface $client) {
        $response  = "HTTP/1.1 200 OK\r\n";
        $response .= "Content-Length: 12\r\n";
        $response .= "Connection: close\r\n";
        $response .= "\r\n";
        $response .= "Hello world!";
        
        yield $client->write($response);
        
        $client->close();
    };
    
    try {
        while ($server->isOpen()) {
            $coroutine = new Coroutine($generator(yield $server->accept()));
        }
    } catch (Exception $e) {
        echo "Error: {$e->getMessage()}\n";
    }
};

$coroutine = new Coroutine($generator($server));

Loop::run();
```

## Documentation

- [SocketInterface](#socketinterface)
    - [isOpen()](#isopen) - Determines if the socket is open.
    - [close()](#close) - Closes the socket.
- [Server](#server)
    - [Server Constructor](#server-constructor) - Creates a server from a stream socket server resource.
    - [accept()](#accept) - Returns a promise fulfilled when a client connects.
    - [getAddress()](#getaddress) - Returns the address of the server.
    - [getPort()](#getport) - Returns the port of the server.
- [ServerFactory](#serverfactory)
    - [create()](#create) - Creates a server on a given host and port.
- [ReadableStream](#readablestream)
    - [ReadableStream Constructor](#readablestream-constructor) - Creates a readable stream from a stream socket resource.
- [WritableStream](#writablestream)
    - [WritableStream Constructor](#writablestream-constructor) - Creates a writable stream from a stream socket resource.
- [DuplexStream](#readablestream)
    - [DuplexStream Constructor](#duplexstream-constructor) - Creates a duplex stream from a stream socket resource.
- [Client](#client)
    - [Client Constructor](#client-constructor) - Creates a client from a stream socket resource.
    - [enableCrypto()](#enablecrypto) - Enables crypto on the client.
    - [getLocalAddress()](#getlocaladdress) - Returns the local address of the client.
    - [getLocalPort()](#getlocalport) - Returns the local port of the client.
    - [getRemoteAddress()](#getremoteaddress) - Returns the remote address of the client.
    - [getRemotePort()](#getremoteport) - Returns the remote port of the client.
- [Connector](#connector)
    - [connect()](#connect) - Returns a promise fulfilled with a `Client` object when a connection is established.

#### Function prototypes

Prototypes for object instance methods are described below using the following syntax:

```php
ReturnType $classOrInterfaceName->methodName(ArgumentType $arg1, ArgumentType $arg2)
```

Prototypes for static methods are described below using the following syntax:

```php
ReturnType ClassName::methodName(ArgumentType $arg1, ArgumentType $arg2)
```

Note that references in the prototypes below to `PromiseInterface` refer to `Icicle\Promise\PromiseInterface` (see the [Promise API documentation](../Promise) for more information).

## SocketInterface

All classes in this component implement `Icicle\Socket\SocketInterface`.

#### isOpen()

```php
bool $socketInterface->isOpen()
```

Determines if the socket is still open (connected).

---

#### close()

```php
void $socketInterface->close()
```

Closes the socket, making it unreadable or unwritable.

## Server

The `Icicle\Socket\Server\Server` class implements `Icicle\Socket\Server\ServerInterface`, a promise-based interface for creating a TCP server and accepting connections.

#### Server Constructor

```php
$server = new Server(resource $socket)
```

Creates a server from a stream socket server resource generated from `stream_socket_server()`. Generally it is better to use `Icicle\Socket\Server\ServerFactory` to create a `Icicle\Socket\Server\Server` instance.

---

#### accept()

```php
PromiseInterface $serverInterface->accept(float|null $timeout)
```

Returns a promise that is fulfilled with a `Icicle\Socket\Client\ClientInterface` object when a connection is accepted. If `$timeout` is not null, the promise is rejected after `$timeout` seconds if no connections are made to the server. (Note that `Icicle\Socket\Server\Server` can be easily extended and modified to fulfill accept requests with different objects implementing `Icicle\Socket\Client\ClientInterface`.)

Resolution | Type | Description
:-: | :-- | :--
Fulfilled | `Icicle\Socket\ClientInterface` | Accepted client object.
Rejected | `Icicle\Socket\Exception\AcceptException` | If accepting a client fails.
Rejected | `Icicle\Socket\Exception\BusyException` | If the server already had an accept pending.
Rejected | `Icicle\Stream\Exception\UnavailableException` | If the server was previously closed.
Rejected | `Icicle\Stream\Exception\ClosedException` | If the server is closed during pending accept.
Rejected | `Icicle\Socket\Exception\TimeoutException` | If the timeout expires.

---

#### getAddress()

```php
string $serverInterface->getAddress()
```

Returns the local IP address as a string.

---

#### getPort()

```php
int $serverInterface->getPort()
```

Returns the local port.

## ServerFactory

`Icicle\Socket\Server\ServerFactory` (implements `Icicle\Socket\Server\ServerFactoryInterface`) can be used to create server instances from a hostname, port, and list of options.

#### create()

```php
Server $serverFactory->create(string $host, int $port, mixed[] $options = null)
```

Creates a server bound and listening on the given host and port.

Option | Type | Description
:-- | :-- | :--
`backlog` | `int` | Connection backlog size. Note that the operating system variable `SOMAXCONN` may set an upper limit and may need to be changed to allow a larger backlog size.
`pem` | `string` | Path to PEM file containing certificate and private key to enable SSL on client connections.
`passphrase` | `string` | PEM passphrase if applicable.
`name` | `string` | Name to use as SNI identifier. If not set, name will be guessed based on `$host`.

## ReadableStream

`Icicle\Socket\Stream\ReadableStream` implements `Icicle\Stream\ReadableStreamInterface`, so it is interoperable with any other class implementing one of the stream interfaces.

See the [ReadableStreamInterface API documentation](../Stream#readablestreaminterface) for more information on how readable streams are used.

`Icicle\Socket\Stream\ReadableStream` also implements `Icicle\Socket\Stream\ReadableSocketInterface`, which adds an optional parameter `float|null $timeout = null` as the last parameter to the methods `read()`, `poll()`, and `pipe()`. If this parameter is not `null`, the promise returned by these methods will be rejected with `Icicle\Socket\Exception\TimeoutException` if no data is received on the socket within the number of seconds given.

When the other end of the connection is closed and a read is pending, that read will be fulfilled with an empty string. Subsequent reads will then reject with an instance of `Icicle\Stream\Exception\UnreadableException` and `isReadable()` will return `false`.

#### ReadableStream Constructor

```php
$stream = new ReadableStream(resource $socket)
```

Creates a readable stream from the given stream socket resource.

## WritableStream

`Icicle\Socket\Stream\WritableStream` implements `Icicle\Stream\WritableStreamInterface`, so it is interoperable with any other class implementing one of the stream interfaces.

See the [WritableStreaminterface API documentation](../Stream#writablestreaminterface) for more information on how writable streams are used.

`Icicle\Socket\Stream\WritableStream` also implements `Icicle\Socket\Stream\WritableSocketInterface`, which adds an optional parameter `float|null $timeout = null` as the last parameter to the methods `write()`, `await()`, and `end()`. If this parameter is not `null`, the promise returned by these methods will be rejected with `Icicle\Socket\Exception\TimeoutException` if no data is received on the socket within the number of seconds given.

#### WritableStream Constructor

```php
$stream = new WritableStream(resource $socket)
```

Creates a writable stream from the given stream socket resource.

## DuplexStream

`Icicle\Socket\Stream\DuplexStream` implements `Icicle\Stream\DuplexStreamInterface`, making it both a readable stream and a writable stream. It also implements `Icicle\Socket\Stream\DuplexSocketInterface`, adding an optional parameter `float|null $timeout = null` to the stream methods as described in the sections above on [ReadableStream](#readablestream) and [WritableStream](#writablestream).

See the [ReadableStreamInterface API documentation](../Stream#readablestreaminterface) and [WritableStreamInterface API documentation](../Stream#writablestreaminterface) for more information on how duplex streams are used.


#### DuplexStream Constructor

```php
$stream = new DuplexStream(resource $socket)
```

Creates a duplex stream from the given stream socket resource.

## Client

`Icicle\Socket\Client\Client` objects implement `Icicle\Socket\Client\ClientInterface` and are used as the fulfillment value of the promise returned by `Icicle\Socket\Server\Server::accept()` ([see documentation above](#accept)). (Note that `Icicle\Socket\Server\Server` can be easily extended and modified to fulfill accept requests with different objects implementing `Icicle\Socket\Client\ClientInterface`.)

The class extends `Icicle\Socket\Stream\DuplexStream`, so it inherits all the readable and writable stream methods as well as adding those below.

#### Client Constructor

```php
$client = new Client(resource $socket)
```

---

Creates a client object from the given stream socket resource.

#### enableCrypto()

```php
PromiseInterface $clientInterface->enableCrypto(int $method, float|null $timeout = null)
```

Enables encryption on the socket. For objects created from `Icicle\Socket\Server\Server::accept()`, a PEM file must have been provided when creating the server socket (see `Icicle\Socket\Server\ServerFactory`). Use the `STREAM_CRYPTO_METHOD_*_SERVER` constants when enabling crypto on remote clients (e.g., created by `Icicle\Socket\Server\ServerInterface::accept()`) and the `STREAM_CRYPTO_METHOD_*_CLIENT` constants when enabling crypto on a local client connection (e.g., created by `Icicle\Socket\Client\ConnectorInterface::connect()`).

---

#### getLocalAddress()

```php
string $clientInterface->getLocalAddress()
```

Returns the local IP address as a string.

---

#### getLocalPort()

```php
int $clientInterface->getLocalPort()
```

Returns the local port.

---

#### getRemoteAddress()

```php
string $clientInterface->getRemoteAddress()
```

Returns the remote IP address as a string.

---

#### getRemotePort()

```php
int $clientInterface->getRemotePort()
```

Returns the remote port.

## Connector

The `Icicle\Socket\Client\Connector` class (implements `Icicle\Socket\Client\ConnectorInterface`) asynchronously connects to a remote server, returning a promise that is fulfilled with an instance of `Icicle\Socket\Client\Client` when the connection is successfully established. Note that the *host should be given as an IP address*, as DNS lookups performed by PHP are synchronous (blocking). If you wish to use domain names instead of IPs, see `Icicle\Dns\Connector\Connector` in the [DNS component](//github.com/icicleio/Dns).

#### connect()

```php
PromiseInterface $connector->connect(string $host, int $port, mixed[] $options = null)
```

Connects asynchronously to the given host on the given port.

Option | Type | Description
:-- | :-- | :--
`protocol` | `string` | The protocol to use, such as tcp, udp, s3, ssh. Defaults to tcp.
`timeout` | `float` | Number of seconds until connection attempt times out. Defaults to 10 seconds.
`cn` | `string` | Host name (common name) used to verify certificate. e.g., `*.google.com`
`allow_self_signed` | `bool` | Set to `true` to allow self-signed certificates. Defaults to `false`.
`max_depth` | `int` | Max levels of certificate authorities the verifier will transverse. Defaults to 10.
`cafile` | `string` | Path to bundle of root certificates to verify against.

Resolution | Type | Description
:-: | :-- | :--
Fulfilled | `Icicle\Socket\ClientInterface` | Fulfilled once the connection is established.
Rejected | `Icicle\Socket\Exception\InvalidArgumentException` | If the `cafile` option does not exist at the given path.
Rejected | `Icicle\Stream\Exception\FailureException` | If the connection attempt fails (such as an invalid host).
Rejected | `Icicle\Socket\Exception\TimeoutException` | If the connection attempt times out.

# Streams

Streams represent a common promise-based API that may be implemented by classes that read or write sequences of binary data to facilitate interoperability. The stream component defines three interfaces, one of which should be used by all streams.

- `Icicle\Stream\ReadableStreamInterface`: Interface to be used by streams that are only readable.
- `Icicle\Stream\WritableStreamInterface`: Interface to be used by streams that are only writable.
- `Icicle\Stream\DuplexStreamInterface`: Interface to be used by streams that are readable and writable. Extends both `Icicle\Stream\ReadableStreamInterface` and `Icicle\Stream\WritableStreamInterface`.

## Documentation

- [StreamInterface](#streaminterface) - Basic stream interface.
    - [isOpen()](#isopen) - Determines if the stream is still open.
    - [close()](#close) - Closes the stream.
- [ReadableStreamInterface](#readablestreaminterface) - Interface for readable streams.
    - [read()](#read) - Read data from the stream.
    - [readTo()](#readto) - Read data from the stream until a particular byte is found.
    - [poll()](#poll) - Notifies when data is available without consuming it.
    - [pipe()](#pipe) - Pipes data from this stream to a writable stream.
    - [pipeTo()](#pipeTo) - Pipes data from this stream to a writable stream until a particular byte is found.
    - [isReadable()](#isreadable) - Determines if the stream is readable.
- [WritableStreamInterface](#writablestreaminterface) - Interface for writable streams.
    - [write()](#write) - Writes data to the stream.
    - [await()](#await) - Notifies when a stream is available for writing.
    - [end()](#end) - Writes data to the stream then closes the stream.
    - [isWritable()](#isWritable)
- [DuplexStreamInterface](#duplexstreaminterface) - Interface for streams that are readable and writable.
- [Stream](#stream) - Buffer that implements `Icicle\Stream\DuplexStreamInterface`.

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

## StreamInterface

All other stream interfaces extend this basic interface.

#### isOpen()

```php
bool $streamInterface->isOpen()
```

Determines if the stream is still open. A closed stream will be neither readable or writable.

---

#### close()

```php
void $streamInterface->close()
```

Closes the stream. Once closed, a stream will no longer be readable or writable.

## ReadableStreamInterface

#### read()

```php
PromiseInterface $readableStreamInterface->read(
    int|null $length = null,
    string|int|null $byte = null
)
```

Returns a promise that is fulfilled with data read from the stream when data becomes available. If `$length` is `null`, the promise is fulfilled with any amount of data available on the stream. If `$length` is not `null` the promise will be fulfilled with a maximum of `$length` bytes, but it may be fulfilled with fewer bytes. If the `$byte` parameter is not `null`, reading will stop once the given byte is encountered in the string. The byte matched by `$byte` will be included in the fulfillment string. `$byte` should be a single byte or the integer value of the byte (e.g., `0xa` for the newline character). If a multibyte string is provided, only the first byte will be used.

Resolution | Type | Description
:-: | :-- | :--
Fulfilled | `string` | Any number of bytes or up to `$length` bytes if `$length` was not `null`.
Rejected | `Icicle\Stream\Exception\BusyException` | If a read was already pending on the stream.
Rejected | `Icicle\Stream\Exception\UnreadableException` | If the stream is no longer readable.
Rejected | `Icicle\Stream\Exception\ClosedException` | If the stream has been closed.

---

#### poll()

```php
PromiseInterface $readableStreamInterface->poll()
```

Resolution | Type | Description
:-: | :-- | :--
Fulfilled | `string` | Fulfilled with an empty string once data is available on the stream.
Rejected | `Icicle\Stream\Exception\BusyException` | If a read was already pending on the stream.
Rejected | `Icicle\Stream\Exception\UnreadableException` | If the stream is no longer readable.
Rejected | `Icicle\Stream\Exception\ClosedException` | If the stream has been closed.

Returns a promise that is fulfilled when there is data immediately available on the stream without consuming any data.

---

#### pipe()

```php
PromiseInterface $readableStreamInterface->pipe(
    WritableStreamInterface $stream,
    bool $endOnClose = true,
    int|null $length = null,
    string|int|null $byte = null
)
```

Pipes all data read from this stream to the writable stream. If `$length` is not `null`, only `$length` bytes will be piped to the writable stream. The returned promise is fulfilled with the number of bytes piped once the writable stream closes or `$length` bytes have been piped. If `$byte` is not `null`, piping will end once `$byte` is encountered in the stream.

Resolution | Type | Description
:-: | :-- | :--
Fulfilled | `int` | Fulfilled when the writable stream is no longer writable or when `$length` bytes have been piped or `$byte` is read from the stream.
Rejected | `Icicle\Stream\Exception\BusyException` | If a read was already pending on the stream.
Rejected | `Icicle\Stream\Exception\UnreadableException` | If the stream is no longer readable.
Rejected | `Icicle\Stream\Exception\ClosedException` | If the stream has been closed.

---

#### isReadable()

```php
bool $readableStreamInterface->isReadable()
```

Determines if the stream is readable.

## WritableStreamInterface

#### write()

```php
PromiseInterface $writableStreamInterface->write(string $data)
```

Writes the given data to the stream. Returns a promise that is fulfilled with the number of bytes written once that data has successfully been written to the stream.

Resolution | Type | Description
:-: | :-- | :--
Fulfilled | `int` | Fulfilled with the number of bytes written when the data has actually been written to the stream.
Rejected | `Icicle\Stream\Exception\UnwritableException` | If the stream is no longer writable.
Rejected | `Icicle\Stream\Exception\ClosedException` | If the stream has been closed.

---

#### await()

```php
PromiseInterface $writableStreamInterface->await()
```

Returns a promise that is fulfilled with `0` when the stream is able to receive data for writing.

Resolution | Type | Description
:-: | :-- | :--
Fulfilled | `int` | Fulfilled with `0` when the stream is writable.
Rejected | `Icicle\Stream\Exception\UnwritableException` | If the stream is no longer writable.
Rejected | `Icicle\Stream\Exception\ClosedException` | If the stream has been closed.

#### end()

---

```php
PromiseInterface $writableStreamInterface->end(string|null $data = null)
```

Writes the given data to the stream then immediately closes the stream by calling `close()`.

Resolution | Type | Description
:-: | :-- | :--
Fulfilled | `int` | Fulfilled with the number of bytes written when the data has actually been written to the stream.
Rejected | `Icicle\Stream\Exception\UnwritableException` | If the stream is no longer writable.
Rejected | `Icicle\Stream\Exception\ClosedException` | If the stream has been closed.

---

#### isWritable()

```php
bool $writableStreamInterface->isWritable()
```

Determines if the stream is writable.

## DuplexStreamInterface

A duplex stream is both readable and writable. `Icicle\Stream\DuplexStreamInterface` extends both `Icicle\Stream\ReadableStreamInterface` and `Icicle\Stream\WritableStreamInterface`, and therefore inherits all the methods above.

## Stream

`Icicle\Stream\Stream` objects act as a buffer that implements `Icicle\Stream\DuplexStreamInterface`, allowing consumers to be notified when data is available in the buffer. This class by itself is not particularly useful, but it can be extended to add functionality upon reading or writing, as well as acting as an example of how stream classes can be implemented.

Anything written to an instance of `Icicle\Stream\Stream` is immediately readable.

```php
use Icicle\Loop\Loop;
use Icicle\Stream\Stream;

$stream = new Stream();

$stream
    ->write("This is just a test.\nThis will not be read.")
    ->then(function () use ($stream) {
        return $stream->read(null, "\n");
    })
    ->then(function ($data) {
        echo $data; // Echoes "This is just a test."
    });

Loop::run();
```

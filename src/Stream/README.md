# Streams

Streams represent a common promise-based API that may be implemented by classes that read or write sequences of binary data to facilitate interoperability. The stream component defines three interfaces, one of which should be used by all streams.

- `Icicle\Stream\ReadableStreamInterface`: Interface to be used by streams that are only readable.
- `Icicle\Stream\WritableStreamInterface`: Interface to be used by streams that are only writable.
- `Icicle\Stream\DuplexStreamInterface`: Interface to be used by streams that are readable and writable. Extends both `Icicle\Stream\ReadableStreamInterface` and `Icicle\Stream\WritableStreamInterface`.

## Documentation

- [ReadableStreamInterface](#readablestreaminterface)
    - [read()](#readablestreaminterface-read)
    - [readTo()](#readablestreaminterface-readto)
    - [poll()](#readablestreaminterface-poll)
    - [isReadable()](#readablestreaminterface-isreadable)
    - [pipe()](#readablestreaminterface-pipe)
    - [pipeTo()](#readablestreaminterface-pipeTo)
- [WritableStreamInterface](#writablestreaminterface)
- [DuplexStreaminterface](#duplexstreaminterface)

## ReadableStreamInterface

Note that references in the prototypes below to PromiseInterface refer to `Icicle\Promise\PromiseInterface` (see the [Promise API documentation](../Promise) for more information).

#### ReadableStreamInterface->read()

``` php
PromiseInterface ReadableStreamInterface->read(int|null $length = null)
```

Returns a promise that is fulfilled with data read from the stream when data becomes available. If `$length` is `null`, the promise is fulfilled with any amount of data available on the stream. If `$length` is not `null` the promise will be fulfilled with a maximum of `$length` bytes, but it may be fulfilled with fewer bytes.

Fulfill: `string`: Any number of bytes or up to `$length` bytes if `$length` was not `null`.
Reject: `Icicle\Stream\Exception\BusyException`: If a read was already pending on the stream.
Reject: `Icicle\Stream\Exception\UnreadableException`: If the stream is no longer readable.
Reject: `Icicle\Stream\Exception\ClosedException`: If the stream has been closed.

#### ReadableStreamInterface->readTo()

``` php
PromiseInterface ReadableStreamInterface->readTo(int|string $byte, int|null $length = null)
```

Similar to `read()`, but reading will stop if `$byte` is found in the stream. `$byte` should be a single character (byte) string or the integer value of the byte (e.g., `0xa` for the newline character). If a multibyte string is provided, only the first byte will be used. If `$length` is `null`, the promise is fulfilled with any amount of data available on the stream. If `$length` is not `null` the promise will be fulfilled with a maximum of `$length` bytes, but it may be fulfilled with fewer bytes.

Fulfill: `string`: Any number of bytes or up to `$length` bytes if `$length` was not `null`. Stops reading once `$byte` is read from the stream. `$byte` is included in the result, but may not be in the result if it was not read from the stream.
Reject: `Icicle\Stream\Exception\BusyException`: If a read was already pending on the stream.
Reject: `Icicle\Stream\Exception\UnreadableException`: If the stream is no longer readable.
Reject: `Icicle\Stream\Exception\ClosedException`: If the stream has been closed.

#### ReadableStreamInterface->poll()

``` php
PromiseInterface ReadableStreamInterface->poll()
```

Fulfill: `null`: Fulfilled once data is available on the stream.
Reject: `Icicle\Stream\Exception\BusyException`: If a read was already pending on the stream.
Reject: `Icicle\Stream\Exception\UnreadableException`: If the stream is no longer readable.
Reject: `Icicle\Stream\Exception\ClosedException`: If the stream has been closed.

Returns a promise that is fulfilled when there is data immediately available on the stream without consuming any data.

#### ReadableStreamInterface->pipe()

``` php
PromiseInterface ReadableStreamInterface->pipe(
    WritableStreamInterface $stream,
    bool $endOnClose = true,
    int|null $length = null
)
```

Pipes all data read from this stream to the writable stream. If `$length` is not `null`, only `$length` bytes will be piped to the writable stream. The returned promise is fulfilled with the number of bytes piped once the writable stream closes or `$length` bytes have been piped.

#### ReadableStreamInterface->pipeTo()

``` php
PromiseInterface ReadableStreamInterface->pipeTo(
    WritableStreamInterface $stream,
    int|string $byte,
    bool $endOnClose = true,
    int|null $length = null
)
```

Pipes all data read from this stream to the writable stream until `$byte` is read from the string. If `$length` is not `null`, a maximum `$length` bytes will be piped to the writable stream, regardless of if `$byte` was found in the stream. The returned promise is fulfilled with the number of bytes piped once the writable stream closes or `$length` bytes have been piped.

## WritableStreamInterface

## DuplexStreamInterface

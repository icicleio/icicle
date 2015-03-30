# Changelog

### v0.2.0

- Increased minimum PHP version to 5.5 so external components can be fully compatible with Icicle and make use of Coroutines. This will avoid compatibility confusion and allow for faster development of additional components.
- Refactored loop implementations to make use of [Managers](../src/Loop/Manager). These interfaces and classes are implementation details of each loop, as these objects are never revealed to the user. However, this change allowed `Icicle\Loop\LoopInterface` to be much simpler (as event objects now interact with the manager object instead of the loop object) and exposes only methods that should be publicly available.
- `Icicle\Loop\Events\PollInterface` and `Icicle\Loop\Events\AwaitInterface` have been eliminated in favor of a single object, `Icicle\Loop\Events\SocketEventInterface`. `Icicle\Loop\Loop::poll()` and `Icicle\Loop\Loop::await()` (and the corresponding methods in objects implementing `Icicle\Loop\LoopInterface`) both return an object implementing this interface. The mode (read or write) the returned object uses when listening on the given socket depends on which method created the `Icicle\Loop\Events\SocketEventInterface` object.
- Reorganized the Socket component into more specific namespaces under `Icicle\Socket`: `Client`, `Datagram`, `Server`, and `Stream`.
- Removed static constructors from Server, Datagram, and Client classes, replacing them with interfaced factories.
    - `Icicle\Socket\Server\ServerFactory`: Creates a server (TCP server) on a given host and port.
    - `Icicle\Socket\Datagram\DatagramFactory`: Creates a datagram (UDP server) on a given host and port.
    - `Icicle\Socket\Client\Connector`: Connects to a given host and port, with various options to control how the connection is created.
- `Icicle\Stream\ReadableStreamInterface::readTo()` was eliminated, and the behavior added to `Icicle\Stream\ReadableStreamInterface::read()`. A second parameter, `$byte` was added  to `Icicle\Stream\ReadableStreamInterface::read()` that provides the same behavior. This parameter defaults to `null`, allowing any bytes to be read.
- Similar to above, `Icicle\Stream\ReadableStreamInterface::pipeTo()` was eliminated and a `$byte` parameter added to `Icicle\Stream\ReadableStreamInterface::pipe()`.
- Stream socket classes implementing `Icicle\Stream\ReadableStreamInterface::read()` (`Icicle\Socket\Stream\ReadableStream` and `Icicle\Socket\Stream\DuplexStream`) now fulfill with an empty string when the socket closes instead of rejecting with an `Icicle\Socket\Exception\EofException`. Subsequent reads will still reject with an `Icicle\Stream\Exception\UnreadableException`. This makes reading from stream in a Coroutine easier (no need for a try/catch block to only catch normal closing of the connection if looping while checking `Icicle\Stream\ReadableStreamInterface::isReadable()`).
- `Promise::iterate()` and `Promise::retry()` were modified to better handle cancellation. Cancelling the promise returned by these methods will now also cancel any promises generated internally.

---

### v0.1.3

- Added `Promise::retry()` method for retrying a promise-returning operation if the promise is rejected.

---

### v0.1.2

- Updated the behavior the Socket component to be similar the stream socket implementation in PHP 7. `Icicle\Socket\ReadableStreamTrait::poll()` will fulfill with an empty string even if the connection has closed (stream socket is at EOF) instead of closing the stream and rejecting the promise. This change was made to match the stream socket behavior in PHP 7 that will not return true on `feof()` until a read has been attempted past EOF.

---

### v0.1.1

- Moved Process into separate branch for further development. Will likely be released as a separate package in the future.

---

### v0.1.0

- Initial Release.

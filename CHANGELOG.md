# Changelog

### v0.9.0

- Changes
    - All interface names have been changed to remove the `Interface` suffix. Most interfaces simply had the suffix removed, but there are a few exceptions - more below.
    - *Promises* are now *Awaitables*
        - The `Icicle\Promise` namespace has been renamed to `Icicle\Awaitable`. `Icicle\Promise\PromiseInterface` is now `Icicle\Awaitable\Awaitable`.
        - `Icicle\Awaitable\Promise` now extends a new class `Icicle\Awaitable\Future` that implements `Icicle\Awaitable\Awaitable`. `Future` uses protected methods to resolve the awaitable, so it can be extended to create awaitables that are resolved in different ways. The functionality of `Promise` has not changed.
        - `Icicle\Coroutine\Coroutine` now also extends `Icicle\Awaitable\Future`. The functionality of `Coroutine` has not changed, but it should be faster to create a `Coroutine` object. `Icicle\Coroutine\CoroutineInterface` has been removed.
    - Interfaces in the `Icicle\Loop\Events` namespace were removed.
    - `Icicle\Loop\Events\SocketEvent` was renamed to `Icicle\Loop\Events\Io` since more than just 'sockets' can be used.
    - `Icicle\Coroutine\create()` no longer throws if the callback throws or returns a promise, instead it returns a rejected coroutine.

- New Features
    - `Icicle\Awaitable\Delayed` was added as a publicly resolvable awaitable. This type of awaitable should not be returned from public APIs, but rather only used internally within a class or Coroutine to create an awaitable that can be resolved later. So in other words, a class method or function should never return a `Delayed`. In general, methods and functions should not be returning awaitables as part of their public API. The public API should consist of Generators that can be used as Coroutines.
    - `Icicle\Awaitable\Awaitable` now has a method `uncancellable()` that returns an awaitable that cannot be cancelled.

---

### v0.8.3

- New Features
    - Added `Coroutine\run()` function that may be used to create an initial coroutine that runs the rest of the application. This function should not be called in a running event loop.
    - All loop events can now be referenced/unreferenced like timers using the `reference()` and `unreference()` methods on event objects. Only referenced events will prevent an event loop from exiting the `run()` method. Signal events are created as unreferenced, while all other events are created as referenced.

---

### v0.8.2

- Bug Fixes
    - Fixed issue where a promise would report as pending for some time after being cancelled.
    - Timers that are restarted after being unreferenced would become referenced again. This issue has now been fixed.

---

### v0.8.1

- New Features
    - Added `Icicle\Loop\EvLoop` supporting the `ev` extension. This loop is now the default event loop used if the `ev` extension is available.
    - `Icicle\Promise\map()` now accepts any number of arrays like `array_map()`, passing an element of each array as an argument to the callback function.
    
- Bug Fixes
    - Coroutines are paused immediately upon cancellation to ensure execution does not continue after cancellation.

---

### v0.8.0

- New Features
    - The default event loop can be swapped during execution. Normally this is not recommended and will break a program, but it can be useful in certain circumstances (forking, threading).
    - Added the function `Icicle\Loop\with()` that accepts a function that is run in a separate loop from the default event loop (a specific loop instance can be provided to the function). The default loop is blocked while running the loop.
    - `Icicle\Loop\Events\SocketEventInterface` and `Icicle\Loop\Events\SignalInterface` gained a `setCallback()` method that allows the callback invoked when an event occurs to be swapped without needing to create a new event.

- Changes
    - The cancellation callable is no longer passed to the `Icicle\Promise\Promise` constructor, it should be returned from the resolver function passed to the constructor. This change was made to avoid the need to create reference variables to share values between functions. Instead values can just be used in the cancellation function returned from the resolver. The resolver function must return a `callable` or `null`.
    - Cancelling a promise is now an asynchronous task. Calling `Icicle\Promise\Promise::cancel()` does not immediately call the cancellation method (if given), it is called later (like a function registered with `then()`).
    - `Icicle/Promise/PromiseInterface` now includes an `isCancelled()` method. When a promise is cancelled, this method will return true once the promise has been cancelled. Note that if a child promise is rejected due to an `$onRejected` callable throwing after cancelling the parent promise, `isCancelled()` of the child promise will return false because the promise was not cancelled, it was rejected from the `$onRejected` callback.

- Bug Fixes
    - Fixed issue where `Icicle\Loop\SelectLoop` would not dispatch a signal while blocking. The issue was fixed by adding a periodic timer that checks for signals that may have arrived. The interval of this timer can be set with `Icicle\Loop\SelectLoop::signalInterval()`.
 
---

### v0.7.1

- Modified `Icicle\Promise\Promise` for better performance. The modified implementation eliminates the creation of one closure and only creates a queue of callbacks if more than one callback is registered to be invoked on fulfillment or rejection. No changes were made to functionality.

---

### v0.7.0

- Changes
    - Moved Stream and Socket components to separate repositories: [icicleio/stream](https://github.com/icicleio/stream) and [icicleio/socket](https://github.com/icicleio/socket). No API changes were made in these components from v0.6.0. If your project depends on these components, just add them as a requirement with composer.

---

### v0.6.0

- Changes
    - The methods `Icicle\Stream\ReadableStreamInterface::pipe()`, `Icicle\Socket\Client\ClientInterface::enableCrypto()`, and `Icicle\Socket\Client\ConnectorInterface::connect()` now return a `Generator` instead of an `Icicle\Promise\PromiseInterface` object. The returned generator can be yielded directly in a coroutine. A promise can be created from these methods if desired by passing the returned `Generator` to the `Icicle\Coroutine\Coroutine` constructor (e.g., `$coroutine = new Coroutine($connector->connect($host, $port))`. *This change was made for better performance with `yield from` in PHP 7.*
    - `Icicle\Loop\Events\SocketEventInterface::listen()` now uses `0` to indicate no timeout.
    - All stream methods have a `$timeout` parameter, not only stream sockets. All `$timeout` parameters now use `0` to indicate no timeout.
    - `Icicle\Stream\ReadableStreamInterface::read()` and `Icicle\Stream\ReadableStreamInterface::pipe()` now use `0` to specify any number of bytes instead of `null`.
    - `Icicle\Loop\schedule()` was renamed to `Icicle\Loop\queue()` and `Icicle\Loop\maxScheduleDepth()` was renamed to `Icicle\Loop\maxQueueDepth()`. `Icicle\Loop\maxQueueDepth()` requires a `$depth` parameter (use `0` for unlimited). The previous depth is still returned.
    - `Icicle\Coroutine\async()` was renamed to `Icicle\Coroutine\wrap()`.
    - `Icicle\Promise\join()` was renamed to `Icicle\Promise\all()`.
    - Exceptions were remodeled based on the exception hierarchy coming in PHP 7. Each component has a class `Exception` and `Error` that extends `\Exception`. The empty `ExceptionInterface` classes were eliminated. Several classes were renamed to use the Error suffix to indicate they represent coding errors and generally should not be caught except for cleanup/logging.
    - The protocol `unix` no longer needs to be explicitly specified when creating or connecting to a unix socket. Using `null` for the port is sufficient.

---

### v0.5.3

- New Features
    - Added `Icicle\Promise\wait()` function that can be used to synchronously wait for a promise to be resolved. The fulfillment value is returned or the rejection reason is thrown from the function. This function can be used to integrate Icicle into a synchronous environment, but generally should not be used in an active event loop.
- Changes
    - Various performance improvements when executing scheduled callbacks, executing promise callbacks, and checking for coroutine completion or cancellation.
- Bug Fixes
    - Added check in `Icicle\Socket\Datagram\Datagram::send()` on `stream_socket_sendto()` sending 0 bytes if the data was not immediately sent to prevent an infinite loop if the datagram is unexpectedly closed while waiting to send data. 
    - Changed timer execution in `Icicle\Loop\SelectLoop` to avoid timer drift.

---

### v0.5.2

- Changes
    - `Icicle\Stream\ReadableStreamInterface::pipe()` implementations now use coroutines instead of `Icicle\Promise\iterate()`, significantly improving performance. The `Icicle\Coroutine\Coroutine` instance is returned from `pipe()`, so piping may be paused if desired.

---

### v0.5.1

- Bug Fixes
    - Fixed bug related to generators that never yield. If a generator was written in such a way that it may never yield, the coroutine was rejected even though the generator had not been run and could have yielded values under different circumstances. Now if a generator is immediately invalid, the coroutine is fulfilled with `null` instead of being rejected.

---

### v0.5.0

- New Features
    - `Icicle\Socket\Datagram` classes and interfaces added to documentation and can now be used.

- Changes
    - The Loop facade class has been replaced by a set of functions defined in the `Icicle\Loop` namespace. Generally, calls to the Loop facade such as `Loop::run()` can be replaced with `Loop\run()` (using `Icicle\Loop` instead of `Icicle\Loop\Loop`). See the [Loop documentation](https://github.com/icicleio/Icicle/wiki/Loop) for more information.
    - Static functions in `Icicle\Promise\Promise` have been replaced with functions defined in the `Icicle\Promise` namespace. Calls such as `Promise::resolve()` can be replaced with `Promise\resolve()` (using `Icicle\Promise` instead of `Icicle\Promise\Promise`). See the [Promises documentation](https://github.com/icicleio/Icicle/wiki/Promises) for more information.
    - Static functions in `Icicle\Coroutine\Coroutine` have been replaced with functions defined in the `Icicle\Coroutine` namespace. Like promises above, calls such as `Coroutine::async()` can be replaced with `Coroutine\async()` (using `Icicle\Coroutine` instead of `Icicle\Coroutine\Coroutine`). See the [Coroutine documentation](https://github.com/icicleio/Icicle/wiki/Coroutine) for more information.
    - Lazy promises should now be created with the function `Icicle\Promise\lazy()` instead of directly constructing a `LazyPromise` instance. `Icicle\Promise\LazyPromise` has been moved to `Icicle\Promise\Structures\LazyPromise` and should not be created directly, but rather is an implementation detail of `Icicle\Promise\lazy()`.
    - The promise returned from `Icicle\Socket\Server\Server::accept()` will no longer be rejected with an `AcceptException` if accepting the client fails. The timeout option was also removed from this method, as retrying after a failed accept would make the timeout unreliable.
    - Removed tests from distributions after updating other components to no longer depend on test classes from this package. Use the `--prefer-source` option when installing with Composer if you wish to have tests included.

- Bug Fixes
    - Updated stream closing methods to avoid constructing an exception object if there are no pending promises to be rejected.

---

### v0.4.1

- Changes
    - ~~Added tests back into distributions so users could verify their setup and so other components could use base test classes.~~ (Removed in v0.5.0)

---

### v0.4.0

- New Features
    - Process signals are now treated like other loop events and are represented by objects implementing `Icicle\Loop\Events\SignalInterface`. Use the `Icicle\Loop\Loop::signal()` method to create signal event objects. See the [documentation](https://github.com/icicleio/Icicle/wiki/Loop#signal) for more information.
    - Added method `isEmpty()` to `Icicle\Loop\Loop` that determines if there are any events pending in the loop.
    - Support for unix sockets added to `Icicle\Socket\Client\Connector` by passing the file path as the first parameter and `null` for the port and adding `'protocol' => 'unix'` to the array of options.
    - Support for unix sockets also added to `Icicle\Socket\Server\ServerFactory` by passing the file path as the first parameter and `null` for the port and adding `'protocol' => 'unix'` to the array of options.
    - Added ability to restart timers if they were stopped using the `start()` method. Note that the methods on `Icicle\Loop\Events\TimerInterface` have changed (see Changes section).
    - Added `execute()` method to `Icicle\Loop\Events\ImmediateInterface` to execute the immediate again if desired.

- Changes
    - The Event Emitter package is no longer a dependency since process signals have been refactored into event objects (see above). `Icicle\Loop\LoopInterface` no longer extends `Icicle\EventEmitter\EventEmitterInterface`.
    - Related to the above changes to signal handling, following methods have been removed from `Icicle\Loop\Loop`:
        - `addSignalHandler()`
        - `removeSignalHandler()`
        - `removeAllSignalHandlers()`
    - `Icicle\Loop\Events\TimerInterface` changed to support restarting timers. `start()` method added, `cancel()` renamed to `stop()`.
    - `Icicle\Loop\LoopInterface` objects should no longer pass the socket resource to socket event callbacks. This only affects custom implementations of `Icicle\Loop\LoopInterface`, no changes need to be made to code using socket event objects.
    - Changed order of arguments to create timers in `Icicle\Loop\LoopInterface` to be consistent with other methods creating events. Again, this change only will affect custom loop implementations, not user code since argument order did not change in `Icicle\Loop\Loop`.

---

### v0.3.0

- New Features
    - Added interface for seekable streams, `Icicle\Stream\SeekableStreamInterface`, and the class `Icicle\Stream\Sink` that implements the interface and acts as a seekable buffered sink.
    - Added `splat()` method to `Icicle\Promise\PromiseInterface`. If a promise fulfills with an array or `Traversable` object, this method uses the elements of the array as arguments to the given callback function similar to the `...` (splat) operator.
    - Added verify peer options to `Icicle\Socket\Server\ServerFactory`. Normally peer verification is off on the server side, but the options allow it to be turned on if desired.
    - Added `cn` option to `Icicle\Socket\Client\Connector` that defaults to the same value as the `name` option. Needed for PHP 5.5 for certificate validation if the CN name does not exactly match the peer name as SAN support was not implemented until PHP 5.6. (e.g., `'*.google.com'` may be used for the `cn` option to match a wildcard certificate.)

- Changes
    - `Icicle\Stream\Stream` now closes only once all data has been read from the stream if `end()` is called on the stream. The logic for closing the stream was moved to the `send()` method, allowing extending classes to end the stream from an overridden `send()` method instead of calling `end()`, which results in a recursive call to `send()`.
    - `Icicle\Promise\PromiseInterface::tap()` and `Icicle\Promise\PromiseInterface::cleanup()` were changed so that if the callback given to `tap()` or `cleanup()` returns a promise, the promise returned from these methods is not fulfilled until the promise returned from the callback is resolved. If the promise returned from the callback is rejected, the promise returned from these methods is rejected for the same reason.
    - Removed `always()` and `after()` methods from `Icicle\Promise\PromiseInterface`, since these methods encouraged poor practices and should be replaced with `cleanup()` or `done()`.
    - Removed optional `Exception` parameter from `Icicle\Socket\Stream\ReadableStream::close()`, `Icicle\Socket\Stream\WritableStream::close()`, `Icicle\Socket\Stream\DuplexStream::close()`, `Icicle\Socket\Server\Server::close()`, and `Icicle\Socket\Datagram\Datagram::close()`.
    - Removed `poll()` and `await()` methods from stream interfaces. These methods only make sense on stream sockets and relied heavily on the state of the stream. The methods are still available in `Icicle\Socket\Stream\ReadableStreamTrait` and `Icicle\Socket\Stream\WritableStreamTrait` as `protected` methods if needed by extending classes to implement special functionality using the raw PHP stream.
    - Modified implementations of `Icicle\Stream\ReadableStreamInterface::pipe()` to call `end()` on the writable stream once the stream becomes unreadable if `$endWhenUnreadable` is `true`. Prior, `end()` would only be called if the stream was closed.

- Bug Fixes
    - Fixed bug in `Icicle\Stream\PipeTrait` and `Icicle\Socket\Stream\PipeTrait` that would rejected the returned promise even if the stream was closed or ended normally.
    - Fixed circular reference in stream sockets and servers that delayed garbage collection after closing the server or stream.

---

### v0.2.2

- Stream socket classes now implement `Icicle\Socket\Stream\ReadableSocketInterface`, `Icicle\Socket\Stream\WritableSocketInterface`, and `Icicle\Socket\Stream\DuplexSocketInterface`. These interfaces extend the similarly named stream interfaces and `Icicle\Socket\SocketInterface`, explicitly defining the `$timeout` parameter that is available on stream socket classes. This change does not affect compatibility, since the streams still implement the same interfaces as before, but allow for easier type-checking or type-hinting.
- Separated `pipe()` implementations in streams and stream sockets into traits: `Icicle\Stream\PipeTrait` and `Icicle\Socket\Stream\PipeTrait`.
- Added high water mark (HWM) feature to `Icicle\Stream\Stream`. If a HWM is provided to the constructor, writes to the stream will return pending promises if the number of bytes in the stream buffer is greater than the HWM.

---

### v0.2.1

- Added `Promise::adapt()` method to create promises from any object with a `then(callable $onFulfilled, callable $onRejected)` method.

---

### v0.2.0

- Increased minimum PHP version to 5.5 so external components can be fully compatible with Icicle and make use of Coroutines. This will avoid compatibility confusion and allow for faster development of additional components.
- Refactored loop implementations to make use of [Managers](https://github.com/icicleio/icicle/tree/master/src/Loop/Events/Manager). These interfaces and classes are implementation details of each loop, as these objects are never revealed to the user. However, this change allowed `Icicle\Loop\LoopInterface` to be much simpler (as event objects now interact with the manager object instead of the loop object) and exposes only methods that should be publicly available.
- `Icicle\Loop\Events\PollInterface` and `Icicle\Loop\Events\AwaitInterface` have been eliminated in favor of a single object, `Icicle\Loop\Events\SocketEventInterface`. `Icicle\Loop\Loop::poll()` and `Icicle\Loop\Loop::await()` (and the corresponding methods in objects implementing `Icicle\Loop\LoopInterface`) both return an object implementing this interface. The mode (read or write) the returned object uses when listening on the given socket depends on which method created the `Icicle\Loop\Events\SocketEventInterface` object.
- Reorganized the Socket component into more specific namespaces under `Icicle\Socket`: `Client`, `Datagram`, `Server`, and `Stream`.
- Removed static constructors from Server, Datagram, and Client classes, replacing them with interfaced factories.
    - `Icicle\Socket\Server\ServerFactory`: Creates a server (TCP server) on a given host and port.
    - `Icicle\Socket\Datagram\DatagramFactory`: Creates a datagram (UDP server) on a given host and port.
    - `Icicle\Socket\Client\Connector`: Connects to a given host and port, with various options to control how the connection is created.
- `Icicle\Stream\ReadableStreamInterface::readTo()` was eliminated, and the behavior added to `Icicle\Stream\ReadableStreamInterface::read()`. A second parameter, `$byte` was added  to `Icicle\Stream\ReadableStreamInterface::read()` that provides the same behavior. This parameter defaults to `null`, allowing any bytes to be read.
- Similar to above, `Icicle\Stream\ReadableStreamInterface::pipeTo()` was eliminated and a `$byte` parameter added to `Icicle\Stream\ReadableStreamInterface::pipe()`.
- Stream socket classes implementing `Icicle\Stream\ReadableStreamInterface::read()` (`Icicle\Socket\Stream\ReadableStream` and `Icicle\Socket\Stream\DuplexStream`) now fulfill with an empty string when the socket closes instead of rejecting with an `Icicle\Socket\Exception\EofException`. Subsequent reads will still reject with an `Icicle\Stream\Exception\UnreadableException`. This makes reading from stream in a Coroutine easier (no need for a try/catch block to only catch normal closing of the connection).
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

- Initial release.

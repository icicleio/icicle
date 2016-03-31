# Change log
All notable changes to this project will be documented in this file. This project adheres to [Semantic Versioning](http://semver.org/).

## [0.9.6] - 2016-03-31
### Added
- Added `Icicle\execute()` function that takes a callback (may be a normal function, return an `Awaitable`, or be a coroutine) and runs the function within a running event loop. This function may be used to initialize and run a program instead of manually creating an initial `Coroutine` instance and calling `Icicle\Loop\run()`.

### Fixed
- Fixed [#18](https://github.com/icicleio/icicle/issues/18) resulting from `stream_select()` on Windows sometimes removing array keys if only a single socket is in the loop.
- Fixed a bug in `Icicle\Loop\SelectLoop` that could result in the callback on an IO watcher to be erroneously invoked with `$expired` set to `true` if `Io::listen()` is called successively without an event occurring between invocations.

## [0.9.5] - 2016-02-21
### Changed
- Awaitables are now synchronously resolved. Callbacks are immediately invoked when an awaitable is resolved. Callbacks registered with `then()`, `done()` or other methods will invoke the callback immediately if the awaitable has been resolved. This change was made to improve performance in coroutines. If resolving an awaitable in an object, be sure to cleanup the objects state *before* resolving the awaitable. With this change, methods on the object could be called before the method resolving the awaitable finishes. While some changes were needed within this library and some of the basic Icicle packages, application code built on coroutines should see no effects from this change.
- Renamed `signalHandlingEnabled()` to `isSignalHandlingEnabled()` in `Icicle\Loop\Loop` to be consistent with other method naming conventions. The function `Icicle\Loop\signalHandlingEnabled()` was also renamed to `Icicle\Loop\isSignalHandlingEnabled()`.
- Immediates are now invoked only if there are no *active* events in the loop. An active event is one where the event has occurred, but the callback has not yet been invoked. As before, only a single Immediate is invoked before polling streams for data, invoking timers, or checking for signals.
- Cancelled coroutines now continue executing their generator function, allowing coroutines to execute cleanup code in try/catch blocks. As before, if a coroutine is waiting on an awaitable at the time it is cancelled, that awaitable is still cancelled. The cancellation reason is then thrown into the generator.

## [0.9.4] - 2016-01-18
### Added
- Added `of()`, `fail()`, `concat()`, `zip()`, and `range()` functions to the `Icicle\Observable` namespace. See each function for more information.
- Added `reduce()` function to `Icicle\Observable\Observable` that executes an accumulator function on each emitted value, returning an observable that emits the current accumulated value after each invokation of the accumulator.

### Changed
- Passing `null` (now the default argument) to `Icicle\Loop\Loop::maxQueueDepth()` or `Icicle\Loop\maxQueueDepth()` will return the current max queue depth without modifying it.
- `Icicle\Observable\Emitter` can delegate to another instance of `Icicle\Observable\Observable` by emitting the observable. Values from the emitted observable will then be emitted as though they were emitted by the delegating observable. The `$emit` callable will resolve with the return value of the emitted observable.
- `Icicle\Observable\Emitter` now allows multiple coroutines to be created from the `$emit` callable simultaneously. This makes no difference for emitters using `yield` with `$emit`, but simplifies implementations using `$emit` as part of a callback that may be called before the previous value has finished emitting. See `Icicle\Observable\merge()` for an example of a function that uses `$emit` as a callback.
- If an awaitable emitted from `Icicle\Observable\Emitter` is rejected, the observable will fail with the exception used to reject the awaitable.
- `Icicle\Observable\observe()` now takes a callable `$onDisposed` argument that is invoked with the callable passed to the emitting function if the observable is disposed. This function can be used to remove the callable from the event emitter.

### Fixed
- Fixed [#14](https://github.com/icicleio/icicle/issues/14) caused by queued function arguments being retained in the loop until a full tick completed, consuming more memory than necessary. Arguments are now freed immediately after executing each queued function.

## [0.9.3] - 2016-01-04
### Added
- `Icicle\Observable\Emitter` gained an optional `$onDisposed` parameter on the constructor accepting a callback function that is executed if the observable is disposed (either automatically or explicitly). This callback can either be a regular function, return an awaitable, or a coroutine. If the callback function returns an awaitable or is a coroutine, the observable is not disposed until the awaitable resolves. If the callback throws an exception (or the awaitable rejects), that exception will be used to dispose of the observable.

### Changed
- Cancelled awaitables will now report as pending until any cancellation function has been invoked.

### Fixed
- Fixed issue where the coroutine created from a yielded generator in a coroutine would not be cancelled if the parent coroutine was cancelled (issue only affected v0.9.x and v1.x branches).

## [0.9.2] - 2015-12-17
### Changed
- Watchers now pass the watcher object to the callback function when an event occurs. The watcher object is passed as the last function argument (in the case of timers and immediates, the only argument).
- The interface `Icicle\Loop\Watcher\Watcher` has been changed to an abstract class.
- All watchers feature `setData()` and `getData()` methods for setting and getting data associated with the watcher. Functions creating watchers have an optional `$data` parameter that can be used to set the data associated with a watcher when it is created.
- Timers and immediates no longer accept a variadic list of arguments. Instead the timer or immediate object is passed to the callback. Use `getData()` and `setData()` on the watcher for passing data to the callback.

## [0.9.1] - 2015-12-04
### Added
- `Icicle\Loop\Watcher\Timer` gained an `again()` method that will restart the timer as though it were just started even if the timer is currently pending.
- `Icicle\Loop\poll()` and `Icicle\Loop\await()` now have a third parameter that if true (defaults to false) will create a persistent IO watcher object that will remain active once `listen()` is called until `cancel()` is called on the watcher. `Icicle\Loop\Watcher\Io` gained a `isPersistent()` method returning a boolean.

### Changed
- Dropped support for the `event` and `libevent` extensions. These extensions have been replaced by the `ev` extension and are no longer being actively developed.
- Cancelling a coroutine will throw the cancellation reason into the generator and cancel any yielded awaitables.

### Fixes
- Fixed issue where disposing of an observable would not throw the disposal reason from `ObservableIterator::getReturn()`.

## [0.9.0] - 2015-12-02
### Changed
- All interface names have been changed to remove the `Interface` suffix. Most interfaces simply had the suffix removed, but there are a few exceptions - more below.
- *Promises* are now *Awaitables*
    - The `Icicle\Promise` namespace has been renamed to `Icicle\Awaitable`. `Icicle\Promise\PromiseInterface` is now `Icicle\Awaitable\Awaitable`.
    - `Icicle\Awaitable\Promise` (previously `Icicle\Promise\Promise`) now extends a new class `Icicle\Awaitable\Future` that implements `Icicle\Awaitable\Awaitable`. `Future` uses protected methods to resolve the awaitable, so it can be extended to create awaitables that are resolved in different ways. The functionality of `Promise` has not changed.
    - `Icicle\Coroutine\Coroutine` now also extends `Icicle\Awaitable\Future`. The functionality of `Coroutine` has not changed, but it should be faster to create a `Coroutine` object. `Icicle\Coroutine\CoroutineInterface` has been removed.
- The `Icicle\Loop\Events` namespace was renamed to `Icicle\Loop\Watcher`. Interfaces in the namespace were removed except `EventInterface` which was renamed to `Watcher`.
- `Icicle\Loop\Events\SocketEvent` was renamed to `Icicle\Loop\Watcher\Io` since more than just 'sockets' can be used.
- `Icicle\Coroutine\create()` no longer throws if the callback throws or returns a promise, instead it returns a rejected coroutine.
- `Icicle\Awaitable\Awaitable::timeout()` (previously `Icicle\Promise\PromiseInterface::timeout()`) now takes a callback function that is invoked if the parent awaitable is not resolved in the given timeout. The promise returned from this method is resolved by the callback function. This callback function can still cancel the parent promise if desired or perform any other action.
- Rejecting an awaitable now requires an exception instance.
- `Icicle\Awaitable\Awaitable::cancel()` (previously `Icicle\Promise\PromiseInterface::cancel()` now requires an exception instance or null. If null, an instance of `Icicle\Awaitable\Exception\CancelledException` is used.
- `Icicle\Awaitable\Awaitable::timeout()` (previously `Icicle\Promise\PromiseInterface::timeout()` now takes a callable or null as the second argument. The awaitable returned from `timeout()` is resolved by the callable or rejected with an instance of `Icicle\Awaitable\Exception\TimeoutException` if no callable is given.

### Added
- Added observables that represent asynchronous collections. Observables implement `Icicle\Observable\Observable` and include array-like methods including `Observable::map()` and `Observable::filter()`. Observables can be iterated over asynchronously in a coroutine using the iterator returned from `Observable::getIterator()`. See the example in `examples/observable.php` and the documentation (work-in-progress) for more information.
- `Icicle\Awaitable\Delayed` was added as a publicly resolvable awaitable. This type of awaitable should not be returned from public APIs, but rather only used internally within a class or Coroutine to create an awaitable that can be resolved later. So in other words, a class method or function should never return a `Delayed`. In general, methods and functions should not be returning awaitables as part of their public API. The public API should consist of Generators that can be used as Coroutines.
- `Icicle\Awaitable\Awaitable` now has a method `uncancellable()` that returns an awaitable that cannot be cancelled (the `cancel()` method is a no-op).

## [0.8.3] - 2015-09-17
### Added
- Added `Coroutine\run()` function that may be used to create an initial coroutine that runs the rest of the application. This function should not be called in a running event loop.
- All loop events can now be referenced/unreferenced like timers using the `reference()` and `unreference()` methods on event objects. Only referenced events will prevent an event loop from exiting the `run()` method. Signal events are created as unreferenced, while all other events are created as referenced.

## [0.8.2] - 2015-09-09
### Fixed
- Fixed issue where a promise would report as pending for some time after being cancelled.
- Timers that are restarted after being unreferenced would become referenced again. This issue has now been fixed.

## [0.8.1] - 2015-08-28
### Added
- Added `Icicle\Loop\EvLoop` supporting the `ev` extension. This loop is now the default event loop used if the `ev` extension is available.
- `Icicle\Promise\map()` now accepts any number of arrays like `array_map()`, passing an element of each array as an argument to the callback function.
    
### Fixed
- Coroutines are paused immediately upon cancellation to ensure execution does not continue after cancellation.

## [0.8.0] - 2015-08-15
### New Features
- The default event loop can be swapped during execution. Normally this is not recommended and will break a program, but it can be useful in certain circumstances (forking, threading).
- Added the function `Icicle\Loop\with()` that accepts a function that is run in a separate loop from the default event loop (a specific loop instance can be provided to the function). The default loop is blocked while running the loop.
- `Icicle\Loop\Events\SocketEventInterface` and `Icicle\Loop\Events\SignalInterface` gained a `setCallback()` method that allows the callback invoked when an event occurs to be swapped without needing to create a new event.

### Changed
- The cancellation callable is no longer passed to the `Icicle\Promise\Promise` constructor, it should be returned from the resolver function passed to the constructor. This change was made to avoid the need to create reference variables to share values between functions. Instead values can just be used in the cancellation function returned from the resolver. The resolver function must return a `callable` or `null`.
- Cancelling a promise is now an asynchronous task. Calling `Icicle\Promise\Promise::cancel()` does not immediately call the cancellation method (if given), it is called later (like a function registered with `then()`).
- `Icicle/Promise/PromiseInterface` now includes an `isCancelled()` method. When a promise is cancelled, this method will return true once the promise has been cancelled. Note that if a child promise is rejected due to an `$onRejected` callable throwing after cancelling the parent promise, `isCancelled()` of the child promise will return false because the promise was not cancelled, it was rejected from the `$onRejected` callback.

### Fixed
- Fixed issue where `Icicle\Loop\SelectLoop` would not dispatch a signal while blocking. The issue was fixed by adding a periodic timer that checks for signals that may have arrived. The interval of this timer can be set with `Icicle\Loop\SelectLoop::signalInterval()`.

## [0.7.1] - 2015-07-18
### Fixed
- Modified `Icicle\Promise\Promise` for better performance. The modified implementation eliminates the creation of one closure and only creates a queue of callbacks if more than one callback is registered to be invoked on fulfillment or rejection. No changes were made to functionality.

## [0.7.0] - 2015-07-02
### Changes
- Moved Stream and Socket components to separate repositories: [icicleio/stream](https://github.com/icicleio/stream) and [icicleio/socket](https://github.com/icicleio/socket). No API changes were made in these components from v0.6.0. If your project depends on these components, just add them as a requirement with composer.

See the [release list](https://github.com/icicleio/icicle/releases) for more information on previous releases.


[0.9.6]: https://github.com/icicleio/icicle/releases/tag/v0.9.6
[0.9.5]: https://github.com/icicleio/icicle/releases/tag/v0.9.5
[0.9.4]: https://github.com/icicleio/icicle/releases/tag/v0.9.4
[0.9.3]: https://github.com/icicleio/icicle/releases/tag/v0.9.3
[0.9.2]: https://github.com/icicleio/icicle/releases/tag/v0.9.2
[0.9.1]: https://github.com/icicleio/icicle/releases/tag/v0.9.1
[0.9.0]: https://github.com/icicleio/icicle/releases/tag/v0.9.0
[0.8.3]: https://github.com/icicleio/icicle/releases/tag/v0.8.3
[0.8.2]: https://github.com/icicleio/icicle/releases/tag/v0.8.2
[0.8.1]: https://github.com/icicleio/icicle/releases/tag/v0.8.1
[0.8.0]: https://github.com/icicleio/icicle/releases/tag/v0.8.0
[0.7.1]: https://github.com/icicleio/icicle/releases/tag/v0.7.1
[0.7.0]: https://github.com/icicleio/icicle/releases/tag/v0.7.0

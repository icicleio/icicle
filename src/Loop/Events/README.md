# Events

When an event is scheduled in the event loop through the methods `poll()`, `await()`, `timer()`, `periodic()`, and `immediate()`, an object implementing `Icicle\Loop\Events\EventInterface` is returned. These objects provide methods for listening, cancelling, or determining if the event is pending.

## Documentation

- [Poll](#poll) - Used for polling stream sockets for the availability of data.
    - [listen()](#listen) - Listens for data on the stream socket. 
    - [cancel()](#cancel) - Cancels listening for data.
    - [isPending()](#ispending) - Determines if the poll is pending.
    - [free()](#free) - Frees the poll from the associated event loop.
    - [isFreed()](#isfreed) - Determines if the poll has been freed.
    - [setCallback()](#setcallback) - Sets the callback to be executed when the event is active.
- [Await](#await) - Used for awaiting stream sockets for empty buffer space to write data.
    - [listen()](#listen-1) - Listens for the stream socket to be available to write.
    - [cancel()](#cancel-1) - Cancels listening for write availability.
    - [isPending()](#ispending-1) - Determines if the await is pending.
    - [free()](#free-1) - Frees the await from the associated event loop.
    - [isFreed()](#isfreed-1) - Determines if the await has been freed.
    - [setCallback()](#setcallback-1) - Sets the callback to be executed when the event is active.
- [Timer](#timer) - Executes a callback after a interval of time has elapsed.
    - [cancel()](#cancel-2) - Cancels the timer.
    - [isPending()](#ispending-2) - Determines if the timer is pending.
    - [getInterval()](#getinterval) - Gets the interval of the timer.
    - [isPeriodic()](#isperiodic) - Determines if the timer is periodic.
    - [unreference()](#unreference) - Removes the reference the timer from the event loop.
    - [reference()](#reference) - References the timer in the event loop if it was previously unreferenced.
- [Immediate](#immediate) - Executes a callback once no other events are active in the loop.
    - [cancel()](#cancel-3) - Cancels the immediate.
    - [isPending()](#ispending-3) - Determines if the immediate is pending.

#### Function prototypes

Prototypes for object instance methods are described below using the following syntax:

```php
ReturnType ClassName->methodName(ArgumentType $arg1, ArgumentType $arg2)
```

Prototypes for static methods are described below using the following syntax:

```php
ReturnType ClassName::methodName(ArgumentType $arg1, ArgumentType $arg2)
```

To document the expected prototype of a callback function used as method arguments or return types, the documentation below uses the following syntax for `callable` types:

```php
callable<ReturnType (ArgumentType $arg1, ArgumentType $arg2)>
```

## Poll

A poll becomes active when a socket has data available to read, has closed (EOF), or if the timeout provided to `listen()` has expired. The callback function associated with the event should have the prototype `callable<void (resource $socket, bool $expired)>`. This function is called with `$expired` set to `false` if there is data available to read on `$socket`, or with `$expired` set to `true` if waiting for data timed out.

Note that only one poll object can be created at a time for a given socket resource.

All poll objects implement `Icicle\Loop\Events\PollInterface` and should be created by calling `Icicle\Loop\Loop::poll()` as shown below:

```php
use Icicle\Loop\Loop;
// $socket is a stream socket resource.
$poll = Loop::poll($socket, function ($socket, $expired) {
    // Read data from socket or handle timeout.
});
```

See the [Loop component documentation](../#poll) for more information on `Icicle\Loop\Loop::poll()`.

#### listen()

```php
void $pollInterface->listen(float|null $timeout)
```

Listens for data to become available. If `$timeout` is not `null`, the poll callback will be called after `$timeout` seconds with `$expired` set to `true`.

#### cancel()

```php
void $pollInterface->cancel()
```

Stops listening for data to become available.

#### isPending()

```php
bool $pollInterface->isPending()
```

Determines if the poll is listening for data.

#### free()

```php
void $pollInterface->free()
```

Frees the resources allocated to the poll from the event loop. This function should always be called when the poll is no longer needed. Once a poll has been freed, it cannot be used again and another must be recreated for the same socket resource.

#### isFreed()

```php
bool $pollInterface->isFreed()
```

Determines if the poll has been freed from the event loop.

#### setCallback()

```php
void $pollInterface->setCallback(callable<void (resource $socket, bool $expired)> $callback)
```

Sets the callback to be called when the poll becomes active.

## Await

An await becomes active when a socket has space in the buffer available to write or if the timeout provided to `listen()` has expired. The callback function associated with the event should have the prototype `callable<void (resource $socket, bool $expired)>`. This function is called with `$expired` set to `false` if there is data available to read on `$socket`, or with `$expired` set to `true` if waiting for data timed out.

Note that only one await object can be created at a time for a given socket resource.

All await objects implement `Icicle\Loop\Events\AwaitInterface` and should be created by calling `Icicle\Loop\Loop::await()` as shown below:

```php
use Icicle\Loop\Loop;
// $socket is a stream socket resource.
$await = Loop::await($socket, function ($socket, $expired) {
    // Write data to socket or handle timeout.
});
```

See the [Loop component documentation](../#await) for more information on `Icicle\Loop\Loop::await()`.

#### listen()

```php
void $awaitInterface->listen(float|null $timeout)
```

Listens for space in the socket buffer to become available. If `$timeout` is not `null`, the await callback will be called after `$timeout` seconds with `$expired` set to `true`.

#### cancel()

```php
void $awaitInterface->cancel()
```

Stops listening for space to become available in the buffer.

#### isPending()

```php
bool $awaitInterface->isPending()
```

Determines if the await is listening for space to become available.

#### free()

```php
void $awaitInterface->free()
```

Frees the resources allocated to the await from the event loop. This function should always be called when the await is no longer needed. Once an await has been freed, it cannot be used again and another must be recreated for the same socket resource.

#### isFreed()

```php
bool $awaitInterface->isFreed()
```

Determines if the await has been freed from the event loop.

#### setCallback()

```php
void $awaitInterface->setCallback(callable<void (resource $socket, bool $expired)> $callback)
```

Sets the callback to be called when the await becomes active.

## Timer

Timers are used to execute a callback function after an amount of time has elapsed. Timers may be one-time, executing the callback only once, or periodic, executing the callback many times separated by an interval.

Timers implement `Icicle\Loop\Events\TimerInterface` and should be created by calling `Icicle\Loop\Loop::timer()` for one-time timers and `Icicle\Loop\Loop::periodic()` for periodic timers. An example is shown below:

```php
use Icicle\Loop\Loop;
$timer = Loop::timer(1.3, function () {
    // Function executed after 1.3 seconds have elapsed.
});
```

See the [Loop component documentation](../#timer) for more information on `Icicle\Loop\Loop::timer()` and `Icicle\Loop\Loop::periodic()`.

#### cancel()

```php
void $timerInterface->cancel()
```

Cancels the timer. Once a timer is cancelled, it cannot be restarted.

#### isPending()

```php
bool $timerInterface->isPending()
```

Determines if the timer is pending and will be executed in the future.

#### getInterval()

```php
float $timerInterface->getInterval()
```

Returns the number of seconds originally set for the timer interval.

#### isPeriodic()

```php
bool $timerInterface->isPeriodic()
```

Determines if the timer is periodic.

#### unreference()

```php
void $timerInterface->unreference()
```

Removes the reference to the timer from the event loop. That is, if this timer is the only pending event in the loop, the loop will exit (return from `Icicle\Loop\LoopInterface->run()`).

#### unreference()

```php
void $timerInterface->unreference()
```

Adds a reference to the timer in the event loop. If this timer is still pending, the loop will not exit (return from `Icicle\Loop\LoopInterface->run()`). Note when a timer is created, it is referenced by default. This method only need be called if `unreference()` was previously called on the timer.

## Immediate

An immediate schedules a callback to be called when there are no active events in the loop, only executing one immediate per turn of the event loop.

The name immediate is somewhat misleading, but was chosen because of the similar behavior to the `setImmediate()` function available in some implementations of JavaScript. Think of an immediate as a timer that executes when able rather than after a particular interval. 

Immediates implement `Icicle\Loop\Events\ImmediateInterface` and should be created by calling `Icicle\Loop\Loop::immediate()` as shown below:

```php
use Icicle\Loop\Loop;
$immediate = Loop::immmediate(function () {
    // Function executed when no events are active in the event loop.
});
```

See the [Loop component documentation](../#immediate) for more information on `Icicle\Loop\Loop::immediate()`.

#### cancel()

```php
void $immediate->cancel()
```

Cancels the immediate. Once a immediate is cancelled, it cannot be made pending again.

#### isPending()

```php
bool $immediate->isPending()
```

Determines if the immediate is pending and will be executed in the future.

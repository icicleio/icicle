# Events

When an event is scheduled in the event loop through the methods `poll()`, `await()`, `timer()`, `periodic()`, and `immediate()`, an object implementing `Icicle\Loop\Events\EventInterface` is returned. These objects provide methods for listening, cancelling, or determining if the event is pending.

## Documentation

- [Poll](#poll)
    - [listen()](#poll-listen)
    - [cancel()](#poll-cancel)
    - [isPending()](#poll-ispending)
    - [free()](#poll-free)
    - [isFreed()](#poll-isfreed)
    - [setCallback()](#poll-setcallback)
- [Await](#await)
    - [listen()](#await-listen)
    - [cancel()](#await-cancel)
    - [isPending()](#await-ispending)
    - [free()](#await-free)
    - [isFreed()](#await-isfreed)
    - [setCallback()](#await-setcallback)
- [Timer](#timer)
    - [cancel()](#timer-cancel)
    - [isPending()](#timer-ispending)
    - [getInterval()](#timer-getinterval)
    - [isPeriodic()](#timer-isperiodic)
    - [unreference()](#timer-unreference)
    - [reference()](#timer-reference)
- [Immediate](#immediate)
    - [cancel()](#immediate-cancel)
    - [isPending()](#immediate-ispending)

#### Function prototypes

Prototypes for object instance methods are described below using the following syntax:

``` php
ReturnType ClassName->methodName(ArgumentType $arg1, ArgumentType $arg2)
```

Prototypes for static methods are described below using the following syntax:

``` php
ReturnType ClassName::methodName(ArgumentType $arg1, ArgumentType $arg2)
```

To document the expected prototype of a callback function used as method arguments or return types, the documentation below uses the following syntax for `callable` types:

``` php
callable<ReturnType (ArgumentType $arg1, ArgumentType $arg2)>
```

## Poll

A poll becomes active when a socket has data available to read, has closed (EOF), or if the timeout provided to `listen()` has expired. The callback function associated with the event should have the prototype `callable<void (resource $socket, bool $expired)>`. This function is called with `$expired` set to `false` if there is data available to read on `$socket`, or with `$expired` set to `true` if waiting for data timed out.

Note that only one poll object can be created at a time for a given socket resource.

All poll objects implement `Icicle\Loop\Events\PollInterface` and should be created by calling `Icicle\Loop\Loop::poll()` as shown below:

``` php
use Icicle\Loop\Loop;
// $socket is a stream socket resource.
$poll = Loop::poll($socket, function ($socket, $expired) {
    // Read data from socket or handle timeout.
});
```

See the [Loop component documentation](../#looppoll) for more information on `Icicle\Loop\Loop::poll()`.

#### Poll->listen()

``` php
void Poll->listen(float|null $timeout)
```

Listens for data to become available. If `$timeout` is not `null`, the poll callback will be called after `$timeout` seconds with `$expired` set to `true`.

#### Poll->cancel()

``` php
void Poll->cancel()
```

Stops listening for data to become available.

#### Poll->isPending()

``` php
bool Poll->isPending()
```

Determines if the poll is listening for data.

#### Poll->free()

``` php
void Poll->free()
```

Frees the resources allocated to the poll from the event loop. This function should always be called when the poll is no longer needed. Once a poll has been freed, it cannot be used again and another must be recreated for the same socket resource.

#### Poll->isFreed()

``` php
bool Poll->isFreed()
```

Determines if the poll has been freed from the event loop.

#### Poll->setCallback()

``` php
void Poll->setCallback(callable<void (resource $socket, bool $expired)> $callback)
```

Sets the callback to be called when the poll becomes active.

## Await

An await becomes active when a socket has space in the buffer available to write or if the timeout provided to `listen()` has expired. The callback function associated with the event should have the prototype `callable<void (resource $socket, bool $expired)>`. This function is called with `$expired` set to `false` if there is data available to read on `$socket`, or with `$expired` set to `true` if waiting for data timed out.

Note that only one await object can be created at a time for a given socket resource.

All await objects implement `Icicle\Loop\Events\AwaitInterface` and should be created by calling `Icicle\Loop\Loop::await()` as shown below:

``` php
use Icicle\Loop\Loop;
// $socket is a stream socket resource.
$await = Loop::await($socket, function ($socket, $expired) {
    // Write data to socket or handle timeout.
});
```

See the [Loop component documentation](../#loopawait) for more information on `Icicle\Loop\Loop::await()`.

#### Await->listen()

``` php
void Await->listen(float|null $timeout)
```

Listens for space in the socket buffer to become available. If `$timeout` is not `null`, the await callback will be called after `$timeout` seconds with `$expired` set to `true`.

#### Await->cancel()

``` php
void Await->cancel()
```

Stops listening for space to become available in the buffer.

#### Await->isPending()

``` php
bool Await->isPending()
```

Determines if the await is listening for space to become available.

#### Await->free()

``` php
void Await->free()
```

Frees the resources allocated to the await from the event loop. This function should always be called when the await is no longer needed. Once an await has been freed, it cannot be used again and another must be recreated for the same socket resource.

#### Await->isFreed()

``` php
bool Await->isFreed()
```

Determines if the await has been freed from the event loop.

#### Poll->setCallback()

``` php
void Await->setCallback(callable<void (resource $socket, bool $expired)> $callback)
```

Sets the callback to be called when the await becomes active.

## Timer

Timers are used to execute a callback function after an amount of time has elapsed. Timers may be one-time, executing the callback only once, or periodic, executing the callback many times separated by an interval.

Timers implement `Icicle\Loop\Events\TimerInterface` and should be created by calling `Icicle\Loop\Loop::timer()` for one-time timers and `Icicle\Loop\Loop::periodic()` for periodic timers. An example is shown below:

``` php
use Icicle\Loop\Loop;
$timer = Loop::timer(1.3, function () {
    // Function executed after 1.3 seconds have elapsed.
});
```

See the [Loop component documentation](../#looptimer) for more information on `Icicle\Loop\Loop::timer()` and `Icicle\Loop\Loop::periodic()`.

#### Timer->cancel()

``` php
void Timer->cancel()
```

Cancels the timer. Once a timer is cancelled, it cannot be restarted.

#### Timer->isPending()

``` php
bool Timer->isPending()
```

Determines if the timer is pending and will be executed in the future.

#### Timer->getInterval()

``` php
float Timer->getInterval()
```

Returns the number of seconds originally set for the timer interval.

#### Timer->isPeriodic()

``` php
bool Timer->isPeriodic()
```

Determines if the timer is periodic.

#### Timer->unreference()

``` php
void Timer->unreference()
```

Removes the reference to the timer from the event loop. That is, if this timer is the only pending event in the loop, the loop will exit (return from `Icicle\Loop\LoopInterface->run()`).

#### Timer->unreference()

``` php
void Timer->unreference()
```

Adds a reference to the timer in the event loop. If this timer is still pending, the loop will not exit (return from `Icicle\Loop\LoopInterface->run()`). Note when a timer is created, it is referenced by default. This method only need be called if `unreference()` was previously called on the timer.

## Immediate

An immediate schedules a callback to be called when there are no active events in the loop, only executing one immediate per turn of the event loop.

The name immediate is somewhat misleading, but was chosen because of the similar behavior to the `setImmediate()` function available in some implementations of JavaScript. Think of an immediate as a timer that executes when able rather than after a particular interval. 

Immediates implement `Icicle\Loop\Events\ImmediateInterface` and should be created by calling `Icicle\Loop\Loop::immediate()` as shown below:

``` php
use Icicle\Loop\Loop;
$timer = Loop::immmediate(function () {
    // Function executed when no events are active in the event loop.
});
```

See the [Loop component documentation](../#loopimmediate) for more information on `Icicle\Loop\Loop::immediate()`.

#### Immediate->cancel()

``` php
void Immediate->cancel()
```

Cancels the immediate. Once a immediate is cancelled, it cannot be made pending again.

#### Immediate->isPending()

``` php
bool Immediate->isPending()
```

Determines if the immediate is pending and will be executed in the future.

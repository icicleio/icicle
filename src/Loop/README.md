# Loop

The Loop component implements an event loop that is used to schedule functions, run timers, handle signals, and poll sockets.

## Documentation

- [Loop Facade](#loop-facade) - Facade class for accessing the actual event loop instance.
    - [init()](#init) - Initializes the loop facade with an instance of `Icicle\Loop\LoopInterface`.
    - [getInstance()](#getInstance) - Returns the contained loop instance.
    - [run()](#run) - Starts the event loop.
    - [tick()](#tick) - Runs a single tick of the event loop.
    - [isRunning()](#isrunning) - Determines if the loop is running.
    - [stop()](#stop) - Stops the event loop if it is running.
    - [schedule()](#schedule) - Schedules a function to run later.
    - [maxScheduleDepth()](#maxscheduledepth) - Sets the maximum number of scheduled functions to execute per tick.
    - [poll()](#poll) - Creates an `Icicle\Loop\Events\SocketEventInterface` object to listen for data on a stream socket.
    - [await()](#await) - Creates an `Icicle\Loop\Events\SocketEventInterface` object to wait for available space to write on a stream socket.
    - [timer()](#timer) - Creates a one-time timer, returning an `Icicle\Loop\Events\TimerInterface` object.
    - [periodic()](#periodic) - Creates a periodic timer, returning an `Icicle\Loop\Events\TimerInterface` object.
    - [immediate()](#immediate) - Creates an immediate to execute a function, returning an `Icicle\Loop\Events\ImmediateInterface` object.
    - [signalHandlingEnabled()](#signalhandlingenabled) - Determines if signal handling is enabled.
    - [addSignalHandler()](#addsignalhandler) - Adds a signal handler.
    - [removeSignalHandler()](#removesignalhandler) - Removes a signal handler.
    - [removeAllSignalHandlers()](#removeallsignalhandlers) - Removes all signal handlers from one signal or all signals.
    - [reInit()](#reinit) - Re-initializes the loop after a process is forked.
    - [clear()](#clear) - Removes all pending events from the loop.
- [Loop Implementations](#loop-implementations)
- [Events](#events) - An event object is created when an event is scheduled in the loop.
    - [SocketEventInterface](#socketeventinterface) - Used for polling stream sockets for the availability of data or the ability to write.
        - [listen()](#listen) - Listens for data or the ability to write to the stream socket. 
        - [cancel()](#cancel) - Cancels listening for data or ability to write.
        - [isPending()](#ispending) - Determines if the event is pending.
        - [free()](#free) - Frees the event from the associated event loop.
        - [isFreed()](#isfreed) - Determines if the event has been freed.
        - [setCallback()](#setcallback) - Sets the callback to be executed when the event is active.
    - [TimerInterface](#timerinterface) - Executes a callback after a interval of time has elapsed.
        - [cancel()](#cancel-1) - Cancels the timer.
        - [isPending()](#ispending-1) - Determines if the timer is pending.
        - [getInterval()](#getinterval) - Gets the interval of the timer.
        - [isPeriodic()](#isperiodic) - Determines if the timer is periodic.
        - [unreference()](#unreference) - Removes the reference the timer from the event loop.
        - [reference()](#reference) - References the timer in the event loop if it was previously unreferenced.
    - [ImmediateInterface](#immediateinterface) - Executes a callback once no other events are active in the loop.
        - [cancel()](#cancel-2) - Cancels the immediate.
        - [isPending()](#ispending-2) - Determines if the immediate is pending.

#### Function prototypes

Prototypes for object instance methods are described below using the following syntax:

```php
ReturnType $classOrInterfaceName->methodName(ArgumentType $arg1, ArgumentType $arg2)
```

Prototypes for static methods are described below using the following syntax:

```php
ReturnType ClassName::methodName(ArgumentType $arg1, ArgumentType $arg2)
```

To document the expected prototype of a callback function used as method arguments or return types, the documentation below uses the following syntax for `callable` types:

```php
callable<ReturnType (ArgumentType $arg1, ArgumentType $arg2)>
```

## Loop Facade

Any asynchronous code needs to use the same event loop to be interoperable and non-blocking. The loop component provides the `Icicle\Loop\Loop` facade class that should be used to access the single global event loop. This class acts as a container for an instance of `Icicle\Loop\LoopInterface` that actually implements the event loop.

The static methods of the `Icicle\Loop\Loop` facade class are described below.

#### init()

```php
void Loop::init(LoopInterface $loop)
```

This method allows any particular or custom implementation of `Icicle\Loop\LoopInterface` to be used as the event loop. This method should be called before any code that would access the event loop, otherwise a `Icicle\Loop\Exception\InitializedException` will be thrown since the default factory would have already created an event loop.

#### getInstance()

```php
LoopInterface Loop::getInstance()
```

Returns the event loop instance. If one was not created before or specified with `init()`, it will be created when this method is called.

#### run()

```php
bool Loop::run()
```

Runs the event loop until there are no events pending in the loop. Returns true if the loop exited because `stop()` was called or false if the loop exited because there were no more pending events.

This function is generally the last line in the script that starts the program, as this function blocks until there are no pending events.

#### tick()

```php
void Loop::tick(bool $blocking = false)
```

Executes a single turn of the event loop. Set `$blocking` to `true` to block until at least one pending event becomes active, or set `$blocking` to `false` to return immediately, even if no events are executed.

#### isRunning()

```php
bool Loop::isRunning()
```

Determines if the loop is running.

#### stop()

```php
void Loop::stop()
```

Stops the loop if it is running.

#### schedule()

```php
void Loop::schedule(callable<void (mixed ...$args)> $callback, mixed ...$args)
```

Schedules the function `$callback` to be executed later (sometime after leaving the scope calling this method). Functions are guaranteed to be executed in the order queued. This method is useful for ensuring that functions are called asynchronously.

#### maxScheduleDepth()

```php
int Loop::maxScheduleDepth(int $depth = null)
```

Sets the maximum number of scheduled functions to execute on each turn of the event loop. Returns the previous max schedule depth. If `$depth` is `null`, the max schedule depth is not modified, only returned.

#### poll()

```php
SocketEventInterface Loop::poll(resource $socket, callable<void (resource $socket, bool $expired)>)
```

Creates an `Icicle\Loop\Events\SocketEventInterface` object for the given stream socket that will listen for data to become available on the socket.

#### await()

```php
SocketEventInterface Loop::await(resource $socket, callable<void (resource $socket, bool $expired)>)
```

Creates an `Icicle\Loop\Events\SocketEventInterface` object for the given stream socket that will listen for the ability to write to the socket.

#### timer()

```php
TimerInterface Loop::timer(float $interval, callable<void (mixed ...$args)> $callback, mixed ...$args)
```

Creates a timer that calls the function `$callback` with the given arguments after `$interval` seconds have elapsed. The number of seconds can have a decimal component (e.g., `1.2` to execute the callback in 1.2 seconds). Returns an `Icicle\Loop\Events\TimerInterface` object.

#### periodic()

```php
TimerInterface Loop::periodic(float $interval, callable<void (mixed ...$args)> $callback, mixed ...$args)
```

Creates a timer that calls the function `$callback` with the given arguments every `$interval` seconds until cancelled. The number of seconds can have a decimal component (e.g., `1.2` to execute the callback in 1.2 seconds). Returns an `Icicle\Loop\Events\TimerInterface` object.

#### immediate()

```php
ImmediateInterface Loop::immediate(callable<void (mixed ...$args)> $callback, mixed ...$args)
```

Calls the function `$callback` with the given arguments as soon as there are no active events in the loop, only executing one callback per turn of the loop. Returns an `Icicle\Loop\Events\ImmediateInterface` object. Functions are guaranteed to be executed in the order queued.

The name of this function is somewhat misleading, but was chosen because of the similar behavior to the `setImmediate()` function available in some implementations of JavaScript. Think of an immediate as a timer that executes when able rather than after a particular interval. 

#### signalHandlingEnabled()

```php
bool Loop::signalHandlingEnabled()
```

Determines if signals sent to the PHP process can be handled by the event loop. Returns `true` if signal handling is enabled, `false` if not. Signal handling requires the `pcntl` extension to be installed.

#### addSignalHandler()

```php
void Loop::addSignalHandler(int $signo, callable<void (int $signo)>, bool $once = false)
```

Adds a callback to be invoked when the given signal is received by the process. Requires the `pcntl` extension to be installed, otherwise this function is a no-op.

#### removeSignalHandler()

```php
void Loop::removeSignalHandler(int $signo, callable<void (int $signo)>)
```

The given callback will no longer be invoked when the given signal is received by the process. Requires the `pcntl` extension to be installed, otherwise this function is a no-op.

#### removeSignalHandler()

```php
void Loop::removeSignalAllHandlers(int|null $signo)
```

Removes all handlers for the given signal, or all handlers if `$signo` is `null`. Requires the `pcntl` extension to be installed, otherwise this function is a no-op.

#### reInit()

```php
void Loop::reInit()
```

This function should be called by the child process if the process is forked using `pcntl_fork()`.

#### clear()

```php
void Loop::clear()
```

Removes all events from the loop, returning the loop a state like it had just been created.

## Loop Implementations

There are currently three loop classes, each implementing `Icicle\Loop\LoopInterface`. Any custom implementation written must also implement this interface. Custom loop implementations can be used in the [Loop Facade](#init) using the `Icicle\Loop\Loop::init()` method.

- `Icicle\Loop\SelectLoop`: Works with any installation of PHP since it relies only on core functions. Uses `stream_select()` or `time_nanosleep()` depending on the events pending in the loop.
- `Icicle\Loop\EventLoop`: Requires the `event` pecl extension. Preferred implementation for best performance.
- `Icicle\Loop\LibeventLoop`: Requires the `libevent` pecl extension. Also provides better performance than the `SelectLoop` implementation.

While each implementation is different, there should be no difference in the behavior of a program based on the loop implementation used. Note that there may be some differences in the exact timing of the execution of certain events or the order in which different types of events are executed (particularly the ordering of timers and signals). However, programs should not be reliant on the exact timing of callback function execution and therefore should not be affected by these differences. Regardless of implementation, callbacks scheduled with `schedule()` and `immediate()` are always executed in the order queued.

## Events

When an event is scheduled in the event loop through the methods `poll()`, `await()`, `timer()`, `periodic()`, and `immediate()`, an object implementing `Icicle\Loop\Events\EventInterface` is returned. These objects provide methods for listening, cancelling, or determining if the event is pending.

## SocketEventInterface

A socket event is returned from a poll or await call to the event loop. A poll becomes active when a socket has data available to read, has closed (EOF), or if the timeout provided to `listen()` has expired. An await becomes active when a socket has space in the buffer available to write or if the timeout provided to `listen()` has expired. The callback function associated with the event should have the prototype `callable<void (resource $socket, bool $expired)>`. This function is called with `$expired` set to `false` if there is data available to read on `$socket`, or with `$expired` set to `true` if waiting for data timed out.

Note that you may poll and await a stream socket simultaneously, but multiple socket events cannot be made for the same task (i.e., two polling events or two awaiting events).

Socket event objects implement `Icicle\Loop\Events\SocketEventInterface` and should be created by calling `Icicle\Loop\Loop::poll()` to poll for data or `Icicle\Loop\Loop::await()` to wait for the ability to write.

```php
use Icicle\Loop\Loop;

// $socket is a stream socket resource.

$poll = Loop::poll($socket, function ($socket, $expired) {
    // Read data from socket or handle timeout.
});

$poll = Loop::await($socket, function ($socket, $expired) {
    // Write data to socket or handle timeout.
});
```

See the [Loop Facade documentation](#poll) above for more information on `Icicle\Loop\Loop::poll()` and `Icicle\Loop\Loop::await()`.

#### listen()

```php
void $socketEventInterface->listen(float|null $timeout)
```

Listens for data to become available or the ability to write to the socket. If `$timeout` is not `null`, the poll callback will be called after `$timeout` seconds with `$expired` set to `true`.

#### cancel()

```php
void $socketEventInterface->cancel()
```

Stops listening for data to become available or ability to write.

#### isPending()

```php
bool $socketEventInterface->isPending()
```

Determines if the event is listening for data.

#### free()

```php
void $socketEventInterface->free()
```

Frees the resources allocated to the poll from the event loop. This function should always be called when the event is no longer needed. Once an event has been freed, it cannot be used again and another must be recreated for the same socket resource.

#### isFreed()

```php
bool $socketEventInterface->isFreed()
```

Determines if the event has been freed from the event loop.

#### setCallback()

```php
void $socketEventInterface->setCallback(callable<void (resource $socket, bool $expired)> $callback)
```

Sets the callback to be called when the event becomes active.

## TimerInterface

Timers are used to execute a callback function after an amount of time has elapsed. Timers may be one-time, executing the callback only once, or periodic, executing the callback many times separated by an interval.

Timers implement `Icicle\Loop\Events\TimerInterface` and should be created by calling `Icicle\Loop\Loop::timer()` for one-time timers and `Icicle\Loop\Loop::periodic()` for periodic timers. An example is shown below:

```php
use Icicle\Loop\Loop;
$timer = Loop::timer(1.3, function () {
    // Function executed after 1.3 seconds have elapsed.
});
```

See the [Loop Facade documentation](#timer) above for more information on `Icicle\Loop\Loop::timer()` and `Icicle\Loop\Loop::periodic()`.

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

#### reference()

```php
void $timerInterface->reference()
```

Adds a reference to the timer in the event loop. If this timer is still pending, the loop will not exit (return from `Icicle\Loop\LoopInterface->run()`). Note when a timer is created, it is referenced by default. This method only need be called if `unreference()` was previously called on the timer.

## ImmediateInterface

An immediate schedules a callback to be called when there are no active events in the loop, only executing one immediate per turn of the event loop.

The name immediate is somewhat misleading, but was chosen because of the similar behavior to the `setImmediate()` function available in some implementations of JavaScript. Think of an immediate as a timer that executes when able rather than after a particular interval. 

Immediates implement `Icicle\Loop\Events\ImmediateInterface` and should be created by calling `Icicle\Loop\Loop::immediate()` as shown below:

```php
use Icicle\Loop\Loop;
$immediate = Loop::immediate(function () {
    // Function executed when no events are active in the event loop.
});
```

See the [Loop facade documentation](#immediate) above for more information on `Icicle\Loop\Loop::immediate()`.

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

## Throwing Exceptions

Functions scheduled using `Loop::schedule()` or callback functions used for timers, immediates, and socket events should not throw exceptions. If one of these functions throws an exception, it will be thrown from the `Loop::run()` method. These are referred to as *uncatchable exceptions* since there is no way to catch the thrown exception within the event loop. If an exception can be thrown from code within a callback, that code should be surrounded by a try/catch block and the exception handled within the callback.

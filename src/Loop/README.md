# Loop

The Loop component implements an event loop that is used to schedule functions, run timers, handle signals, and poll sockets.

## Documentation

- [Loop Façade](#loop-facade)
    - [init()](#loopinit)
    - [getInstance()](#loopgetInstance)
    - [run()](#looprun)
    - [tick()](#looptick)
    - [isRunning()](#loopisrunning)
    - [stop()](#loopstop)
    - [schedule()](#loopschedule)
    - [maxScheduleDepth()](#loopmaxscheduledepth)
    - [poll()](#looppoll)
    - [await()](#loopawait)
    - [timer()](#looptimer)
    - [periodic()](#loopperiodic)
    - [immediate()](#loopimmediate)
    - [signalHandlingEnabled()](#loopsignalhandlingenabled)
    - [addSignalHandler()](#loopaddsignalhandler)
    - [removeSignalHandler()](#loopremovesignalhandler)
    - [removeAllSignalHandlers()](#loopremoveallsignalhandlers)
    - [reInit()](#loopreinit)
    - [clear()](#loopclear)
- [Loop Implementations](#loop-implementations)
- [Events](#events)

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

## Loop Façade

Any asynchronous code needs to use the same event loop to be interoperable and non-blocking. The loop component provides the `Icicle\Loop\Loop` façade class that should be used to access the single global event loop. This class acts as a container for an instance of `Icicle\Loop\LoopInterface` that actually implements the event loop.

The static methods of the `Icicle\Loop\Loop` façade class are described below.

#### Loop::init()

``` php
void Loop::init(LoopInterface $loop)
```

This method allows any particular or custom implementation of `Icicle\Loop\LoopInterface` to be used as the event loop. This method should be called before any code that would access the event loop, otherwise a `Icicle\Loop\Exception\InitializedException` will be thrown since the default factory would have already created an event loop.

#### Loop::getInstance()

``` php
LoopInterface Loop::getInstance()
```

Returns the event loop instance. If one was not created before or specified with `init()`, it will be created when this method is called.

#### Loop::run()

``` php
bool Loop::run()
```

Runs the event loop until there are no events pending in the loop. Returns true if the loop exited because `stop()` was called or false if the loop exited because there were no more pending events.

This function is generally the last line in the script that starts the program, as this function blocks until there are no pending events.

#### Loop::tick()

``` php
void Loop::tick(bool $blocking = false)
```

Executes a single turn of the event loop. Set `$blocking` to `true` to block until at least one pending event becomes active, or set `$blocking` to `false` to return immediately, even if no events are executed.

#### Loop::isRunning()

``` php
bool Loop::isRunning()
```

Determines if the loop is running.

#### Loop::stop()

``` php
void Loop::stop()
```

Stops the loop if it is running.

#### Loop::schedule()

``` php
void Loop::schedule(callable<void (mixed ...$args)> $callback, mixed ...$args)
```

Schedules the function `$callback` to be executed later (sometime after leaving the scope calling this method). Functions are guaranteed to be executed in the order queued. This method is useful for ensuring that functions are called asynchronously.

#### Loop::maxScheduleDepth()

``` php
int Loop::maxScheduleDepth(int $depth = null)
```

Sets the maximum number of scheduled functions to execute on each turn of the event loop. Returns the previous max schedule depth. If `$depth` is `null`, the max schedule depth is not modified, only returned.

#### Loop::poll()

``` php
PollInterface Loop::poll(resource $socket, callable<void (resource $socket, bool $expired)>)
```

Creates a `Icicle\Loop\Events\PollInterface` object for the given stream socket.

#### Loop::await()

``` php
AwaitInterface Loop::await(resource $socket, callable<void (resource $socket, bool $expired)>)
```

Creates a `Icicle\Loop\Events\AwaitInterface` object for the given stream socket.

#### Loop::timer()

``` php
TimerInterface Loop::timer(float $interval, callable<void (mixed ...$args)> $callback, mixed ...$args)
```

Creates a timer that calls the function `$callback` with the given arguments after `$interval` seconds have elapsed. The number of seconds can have a decimal component (e.g., `1.2` to execute the callback in 1.2 seconds). Returns a `Icicle\Loop\Events\TimerInterface` object.

#### Loop::periodic()

``` php
TimerInterface Loop::periodic(float $interval, callable<void (mixed ...$args)> $callback, mixed ...$args)
```

Creates a timer that calls the function `$callback` with the given arguments every `$interval` seconds until stopped. The number of seconds can have a decimal component (e.g., `1.2` to execute the callback in 1.2 seconds). Returns a `Icicle\Loop\Events\TimerInterface` object.

#### Loop::immediate()

``` php
ImmediateInterface Loop::immediate(callable<void (mixed ...$args)> $callback, mixed ...$args)
```

Calls the function `$callback` with the given arguments as soon as there are no active events in the loop, only executing one callback per turn of the loop. Returns a `Icicle\Loop\Events\ImmediateInterface` object. Functions are guaranteed to be executed in the order queued.

The name of this function is somewhat misleading, but was chosen because of the similar behavior to the `setImmediate()` function available in some implementations of JavaScript. Think of an immediate as a timer that executes when able rather than after a particular interval. 

#### Loop::signalHandlingEnabled()

``` php
bool Loop::signalHandlingEnabled()
```

Determines if signals sent to the PHP process can be handled by the event loop. Returns `true` if signal handling is enabled, `false` if not. Signal handling requires the `pcntl` extension to be installed.

#### Loop::addSignalHandler()

``` php
void Loop::addSignalHandler(int $signo, callable<void (int $signo)>, bool $once = false)
```

Adds a callback to be invoked when the given signal is received by the process. Requires the `pcntl` extension to be installed, otherwise this function is a no-op.

#### Loop::removeSignalHandler()

``` php
void Loop::removeSignalHandler(int $signo, callable<void (int $signo)>)
```

The given callback will no longer be invoked when the given signal is received by the process. Requires the `pcntl` extension to be installed, otherwise this function is a no-op.

#### Loop::removeSignalHandler()

``` php
void Loop::removeSignalAllHandlers(int|null $signo)
```

Removes all handlers for the given signal, or all handlers if `$signo` is `null`. Requires the `pcntl` extension to be installed, otherwise this function is a no-op.

#### Loop::reInit()

``` php
void Loop::reInit()
```

This function should be called by the child process if the process is forked using `pcntl_fork()`.

#### Loop::clear()

``` php
void Loop::clear()
```

Removes all events from the loop, returning the loop a state like it had just been created.

## Loop Implementations

There are currently three loop classes, each implementing `Icicle\Loop\LoopInterface`. Any custom implementation written must also implement this interface.

- `Icicle\Loop\SelectLoop`: Works with any installation of PHP since it relies only on core functions. Uses `stream_select()` or `time_nanosleep()` depending on the events pending in the loop.
- `Icicle\Loop\EventLoop`: Requires the `event` pecl extension. Preferred implementation for best performance.
- `Icicle\Loop\LibeventLoop`: Requires the `libevent` pecl extension. Also provides better performance.

While each implementation is different, there should be no difference in the behavior of a program based on the loop implementation used. Note that there may be some differences in the exact timing of the execution of certain events or the order in which different types of events are executed (particularly the ordering of timers and signals). However, programs should not be reliant on the exact timing of callback function execution and therefore should not be affected by these differences. Regardless of implementation, callbacks scheduled with `schedule()` and `immediate()` are always executed in the order queued.

## Events

When an event is scheduled in the event loop through the methods `poll()`, `await()`, `timer()`, `periodic()`, and `immediate()`, an object implementing `Icicle\Loop\Events\EventInterface` is returned. These objects provide methods for listening, cancelling, or determining if the event is pending.

Please see the [Event API documentation](Events) for information on each specific type of event.

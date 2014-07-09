# Icicle

**Icicle is a PHP library for writing *asynchronous* code using *synchronous* coding techniques.**

Icicle uses [Promises](#promises) and [Coroutines](#coroutines) to facilitate writing asynchronous code using techniques normally used to write synchronous code, such as returning values and throwing exceptions, instead of using nested callbacks typically found in asynchronous code.

#### Library Constructs

- [Promises](#promises): Placeholders for future values of asynchronous operations. Callbacks registered with promises may return values and throw exceptions.
- [Coroutines](#coroutines): Interruptible functions for building asynchronous code using synchronous coding patterns and error handling.
- [Loop (event loop)](#loop): Used to schedule functions, run timers, handle signals, and poll sockets for pending data or available writes.
- [Sockets](#sockets): Implement asynchronous network and file operations.
- [Streams](#streams) Common interface for reading and writing from sockets or transforming data.
- [Timers](#timers): Used to schedule functions for execution after an interval of time or after other available events are handled.
- [Event Emitters](#event-emitters): Allows objects to emit events that execute a set of registered callbacks.

##### Requirements

- PHP 5.4+ (PHP 5.5+ required for [coroutines](#coroutines)).

##### Suggested

- [event pecl extension](http://pecl.php.net/package/event): Allows for the most performant event loop implementation.
- [libevent pecl extension](http://pecl.php.net/package/libevent): Similar to the event pecl extension, it allows for a more performant event loop implementation.
- [libev (ev) pecl extension](http://pecl.php.net/package/ev): Another extension allowing a more performant event loop implementation, though those above should be preferred.
- [pcntl extension](http://www.php.net/manual/en/book.pcntl.php): Enables custom signal handling, process forking, and child process execution. This extension is bundled with PHP but must be enabled at compile time or compiled as an extension.

## Promises

Icicle implements promises based on the [Promises/A+](http://promisesaplus.com) specification, adding support for cancellation.

Promises are objects that act as placeholders for the future value of an asynchronous operation. Pending promises may either be fulfilled with any value (including `null` and exceptions) or rejected with an exception. Once a promise is fulfilled or rejected (resolved) with a value, the promise cannot becoming pending and the resolution value cannot change.

Callback functions are the primary way of accessing the resolution value of promises. Unlike other APIs that use callbacks, **promises provide an execution context to callback functions, allowing callbacks to return values and throw exceptions**.

The `then(callable $onFulfilled = null, callable $onRejected = null)` method is the primary way to register callbacks that receive either the value used to fulfill the promise or the exception used to reject the promise. A promise is returned by `then()`, which is fulfilled with the return value of a callback or rejected if a callback throws an exception.

The `done(callable $onFulfilled = null, callable $onRejected = null)` method registers callbacks that should either consume promised values or handle errors. No value is returned from `done()`. Values returned by callbacks registered using `done()` are ignored and exceptions thrown from callbacks are re-thrown in an uncatchable way.

*[More on using callbacks to interact with promises...](src/Promises#interacting-with-promises)*

```php
use Icicle\Loop\Loop;

$promise1 = doSomethingAsynchronously(); // Asynchronous function returning a promise.

$promise2 = $promise1->then(
    function ($value) { // Called if $promise1 is fulfilled.
        if (null === $value) {
            throw new Exception("Invalid value!"); // Rejects $promise2.
        }
        // Do something with $value and return the modified value.
        return $value; // Fulfills $promise2 with modified $value.
    }
);

$promise2->done(
    function ($value) { // Called if $promise2 is fulfilled.
        echo "Asynchronous task succeeded: {$value}\n";
    },
    function (Exception $exception) { // Called if $promise1 or $promise2 is rejected.
        echo "Asynchronous task failed: {$exception->getMessage()}\n";
    }
);

Loop::run();
```

In the above example, the function `doSomethingAsynchronously()` returns a promise, `$promise1`, which will either be fulfilled or rejected:

- If `$promise1` is fulfilled, the callback function registered in the call to `$promise1->then()` is executed. If `$value` (the resolution value of `$promise1`) is `null`, `$promise2` is rejected with the exception thrown in the callback. Otherwise `$value` is modified and returned, fulfilling `$promise2`. 
- If `$promise1` is rejected, `$promise2` is rejected since no `$onRejected` callback was registered in the call to `$promise1->then()`.

*[More on promise resolution and propagation...](src/Promise#resolution-and-propagation)*

##### Brief overview of promise API features

- Asynchronous resolution (callbacks are not called before `then()` or `done()` return).
- Convenience methods for registering special callbacks to handle promise resolution.
- Lazy execution of promise-creating functions.
- Operations on collections of promises to join, select, iterate, and map to other promises or values.
- Support for promise cancellation.
- Methods to convert synchronous functions or callback-based functions into functions accepting and returning promises.

**[Promise API documentation](src/Promise)**

## Coroutines

Coroutines are interruptible functions implemented using [Generators](http://www.php.net/manual/en/language.generators.overview.php). Coroutines use the `yield` keyword to define interruption points. When a coroutine yields a value, execution of the coroutine is temporarily interrupted, allowing other code to be executed.

When a coroutine yields a [promise](#promises), execution of the coroutine is interrupted until the promise is resolved. If the promise is fulfilled with a value, the yield statement that yielded the promise will take on the resolved value. For example, `$value = (yield Promise::resolve(2.718));` will set `$value` to `2.718` when execution of the coroutine is resumed. If the promise is rejected, the exception used to reject the promise will be thrown into the function at the yield statement. For example, `yield Promise::reject(new Exception())` would behave identically to replacing the yield statement with `throw new Exception();`.

Note that **no callbacks need to be registered** with the promises yielded in a coroutine and **errors are reported using thrown exceptions**, which will bubble up to the calling context if uncaught in the same way exceptions bubble up in synchronous code.

The example below uses the `Coroutine::call()` method to create a `Coroutine` from a callable function returning a `Generator`.

```php
use Icicle\Coroutine\Coroutine;
use Icicle\Loop\Loop;

$coroutine = Coroutine::call(function () {
    try {
        $value = (yield doSomethingAsynchronously());
        if (null === $value) {
            throw new Exception("Invalid value!");
        }
        // Do something with $value.
        echo "Asynchronous task succeeded: {$value}\n";
    } catch (Exception $exception) {
        // Promise returned by doSomethingAsynchronously() was rejected or fulfilled with null.
        echo "Asynchronous task failed: {$exception->getMessage()}\n";
    }
});

Loop::run();
```

The example above does the same thing as the example section on [promises](#promises) above, but instead uses a coroutine to **structure asynchronous code like synchronous code** using a try/catch block, rather than creating and registering callback functions.

A `Coroutine` is also a [promise](#promises). The promise is fulfilled with the last value yielded from the generator (or fulfillment value of the last yielded promise) or rejected if an exception is thrown from the generator. A coroutine may then yield other coroutines, suspending execution until the yielded coroutine has resolved. If a coroutine yields a `Generator`, it will automatically be converted to a `Coroutine` and handled in the same way as a yielded coroutine.

**[Coroutine API documentation](src/Coroutine)**

## Loop

The event loop schedules functions, runs timers, handles signals, and polls sockets for pending reads and available writes. There are several event loop implementations available depending on what PHP extensions are available. The `SelectLoop` class uses only core PHP functions, so it will work on any PHP installation, but is not as performant as some of the other available implementations. All event loops implement the `LoopInterface` and provide the same features.

The default event loop should be accessed via the static methods of the `Loop` class. The `Loop::init()` method allows a specific or custom implementation of `LoopInterface` to be used as the default event loop.

The following code demonstrates how functions may be scheduled to run later using the `Loop::schedule()` method. Scheduled functions will always be executed in the order scheduled. (Exact timing of the execution of scheduled functions varies and should not be relied upon. See [function schedule timing](src/Loop#schedule-timing) for more details.)

```php
use Icicle\Loop\Loop;

Loop::schedule(function () {
	echo "First.\n";
	Loop::schedule(function () {
	    echo "Second.\n";
	});
	echo "Third.\n";
	Loop::schedule(function () {
		echo "Fourth.\n";
	});
	echo "Fifth.\n";
});

echo "Starting event loop.\n";
Loop::run();
```

The above code will output:

```
Starting event loop.
First.
Third.
Fifth.
Second.
Fourth.
```

The `Loop::run()` method runs the event loop and will not return until the event loop is stopped or no further scheduled functions, timers, or sockets remain in the loop.

**[Loop API documentation](src/Loop)**

## Sockets

**[Sockets API documentation](src/Socket)**

## Streams

**[Streams API documentation](src/Stream)**

## Timers

**[Timers API documentation](src/Timer)**

## Event Emitters

**[Event Emitter API documentation](src/Event)**

## Example

Some example code using coroutines to create an asynchronous echo server.

```php
use Icicle\Coroutine\Coroutine;
use Icicle\Loop\Loop;
use Icicle\Socket\Client;
use Icicle\Socket\Server;

$server = Server::create('localhost', 8080);

$coroutine = Coroutine::async(function (Server $server) {
    $coroutine = Coroutine::async(function (Client $client) {
        try {
            yield $client->write("Hello!\n");
			
            while ($client->isReadable()) {
                $data = (yield $client->read());
                
                yield $client->write($data);
                
                if ("exit\r\n" === $data) {
                    $client->close();
                }
            }
        } catch (Exception $e) {
            $client->close();
        }
    });
    
    while ($server->isOpen()) {
        try {
            $coroutine(yield $server->accept());
        } catch (Exception $e) {
            echo "Error accepting client: {$e->getMessage()}\n";
        }
    }
});

$coroutine($server);

Loop::run();
```

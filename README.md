# Icicle

**Icicle is a library for writing asynchronous code in PHP.**

Icicle uses [Promises](#promises) and [Coroutines](#coroutines) to facilite writing asynchronous code using techniques normally used to write synchronous code, instead of using nested callbacks often found in other asynchronous code.

Icicle also provides:

- [Sockets](#sockets) for performing asynchronous network and file operations.
- Promise-based [streams](#streams) for reading and writing from sockets and manipulating data.
- [Timers](#timers) to schedule functions for execution at particular interval or once other available events are handled.
- [Event emitter](#event-emitter) to allow objects to emit events that execute registered callbacks.

Icicle uses an [event loop](#loop) to schedule functions, run timers, handle signals, and poll sockets for pending data or available writes.

#### Requirements

- PHP 5.4+ (PHP 5.5+ required for [Coroutines](#coroutines)).

#### Suggested

- [event pecl extension](http://pecl.php.net/package/event): Allows for the most performant event loop implementation.
- [libevent pecl extension](http://pecl.php.net/package/libevent): Similar to the event pecl extension, it allows for a more performant event loop implementation.
- [libev (ev) pecl extension](http://pecl.php.net/package/ev): Another extension allowing a more performant event loop implementation, though those above should be preferred.
- [pcntl extension](http://www.php.net/manual/en/book.pcntl.php): Enables custom signal handling, process forking, and child process execution. This extension is bundled with PHP but must be enabled at compile time or compiled as an extension.

## Promises

Icicle implements promises based on the [Promises/A+](http://promisesaplus.com) specification, adding support for cancellation.

Promises are placeholders for future values to be returned by an asynchronous operation. Promises may either be fulfilled with a value (including `null` and exceptions) or rejected with an exception. Once a promise is fulfilled or rejected (resolved), the promises value cannot be changed.

Callback functions are the primary way of accessing the resolution value of promises. Unlike other APIs that use callbacks, *promises provide an execution context to callback functions, allowing callbacks to return values and throw exceptions*. The `then(callable $onFulfilled = null, callable $onRejected = null)` and `done(callable $onFulfilled = null, callable $onRejected = null)` methods are used to register callbacks that receive either the value used to fulfill the promise or the exception used to reject the promise. A promise is returned by `then()`, which is fulfilled with the return value of a callback or rejected if a callback throws an exception. The `done()` method defines callbacks that either consume promised values or handle errors. No value is returned from `done()`. Values returned by callbacks registered using `done()` are ignored and exceptions thrown from callbacks are re-thrown in an uncatchable way.

Calls to `then()` or `done()` do not need to define both callback functions. If `$onFulfilled` or `$onRejected` are omitted from a call to `then()`, the returned promise is either fulfilled or rejected using the same value that was used to resolve the original promise. When omitting the `$onRejected` callback from a call to `done()`, you must be sure the promise cannot be rejected or the exception used to reject the promise will be thrown in an uncatchable way.

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
    function ($value) {
        echo "Asynchronous task succeeded: {$value}\n";
    },
    function (Exception $exception) { // Called if $promise1 or $promise2 is rejected.
        echo "Asynchronous task failed: {$exception->getMessage()}\n";
    }
);

Loop::run();
```

If `$promise1` is fulfilled, the callback function registered in the call to `$promise1->then()` is executed. If `$value` (the resolution value of `$promise1`) is `null`, `$promise2` is rejected with the exception thrown in the callback. Otherwise `$value` is modified and returned, which is then used fulfill `$promise2`. The `$onFulfilled` callback registered in the call to `$promise2->done()` is then called, printing the resolution value of the promise.

If `$promise1` is rejected, `$promise2` is rejected since no `$onRejected` callback was registered in the call to `$promise1->then()`.

If `$promise2` is rejected, the `$onRejected` callback registered in the call to `$promise2->done()` is then executed, printing the exception message.

*For more on how promise resolution values are propagated to registered callbacks, see the section on [resolution and propagation](src/Promise#resolution-and-propagation)*

#### Brief overview of promise API features

- Convenience methods for defining special callbacks to handle promise resolution.
- Lazy execution of promise-creating functions.
- Operations on collections of promises to join, select, iterate, and map to other values.
- Promise cancellation support.
- Convert synchronous functions or callback-based functions into functions accepting and returning promises.

**[Promise API documentation](src/Promise)**

## Coroutines

Coroutines are interruptible functions implemented using [Generators](http://www.php.net/manual/en/language.generators.overview.php). Coroutines use [promises](#promises) as interruption points via the `yield` keyword. Execution of the function is resumed once the yielded Promise is resolved. If the promise is fulfilled with a value, that yield statement which yielded the promise will take on that value. For example, `$value = (yield Promise::resolve(3.14));` will set `$value` to `3.14` when the promise resolves. If the promise is rejected, the Exception used to reject the promise will be thrown into the function at the yield statement.

The example below uses the `call()` static method of the `Coroutine` class to create a `Coroutine` instance from a callable function returning a Generator.

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

This example code does the same thing as the example shown in the section on promises above, but instead uses a coroutine to structure the asynchronous code like synchronous code using a try/catch block, as opposed to creating and registering callback functions.

A `Coroutine` instance is itself a promise, which is fulfilled with the last value yielded from the generator (or fulfillment value of the last yielded promise) or rejected if an exception is thrown from generator.

**[Coroutine API documentation](src/Coroutine)**

## Loop

The event loop schedules functions, runs timers, handles signals, and polls sockets for pending reads and available writes. There are several event loop implementations available depending on what extensions are installed in PHP. `SelectLoop` only requires core PHP functions so it will work on any PHP installation, but is not as performant as some of the other available implementations. All implement the `LoopInterface` and provide the same features.

The default event loop should be accessed via the static methods of the `Loop` class. The `Loop::init()` method allows a specific or custom implementation of `LoopInterface` to be used as the default event loop.

The following code demonstrates how a function may be scheduled to run later (the specific timing varies, see [function schedule timing](src/Loop#schedule-timing) for more details).

```php
use Icicle\Loop\Loop

Loop::schedule(function () {
    echo "First.\n";
});

echo "Second.\n";

Loop::run();
```

The above code will output:

```
Second.
First.
```

**[Loop API documentation](src/Loop)**

## Sockets

**[Sockets API documentation](src/Socket)**

## Streams

**[Streams API documentation](src/Stream)**

## Timers

**[Timers API documentation](src/Timer)**

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

# Icicle

**Icicle is a PHP library for writing *asynchronous* code using *synchronous* coding techniques.**

Icicle uses [Coroutines](#coroutines) built with [Awaitables](#awaitables) to facilitate writing asynchronous code using techniques normally used to write synchronous code, such as returning values and throwing exceptions, instead of using nested callbacks typically found in asynchronous code.

[![Build Status](https://img.shields.io/travis/icicleio/icicle/v1.x.svg?style=flat-square)](https://travis-ci.org/icicleio/icicle)
[![Coverage Status](https://img.shields.io/coveralls/icicleio/icicle/v1.x.svg?style=flat-square)](https://coveralls.io/r/icicleio/icicle)
[![Semantic Version](https://img.shields.io/github/release/icicleio/icicle.svg?style=flat-square)](http://semver.org)
[![MIT License](https://img.shields.io/packagist/l/icicleio/icicle.svg?style=flat-square)](LICENSE)
[![@icicleio on Twitter](https://img.shields.io/badge/twitter-%40icicleio-5189c7.svg?style=flat-square)](https://twitter.com/icicleio)

#### Library Components

- [Coroutines](#coroutines): Interruptible functions for building asynchronous code using synchronous coding patterns and error handling.
- [Awaitables](#awaitables): Placeholders for future values of asynchronous operations. Awaitables can be yielded in coroutines to define interruption points. Callbacks registered with awaitables may return values and throw exceptions.
- [Loop (event loop)](#loop): Used to schedule functions, run timers, handle signals, and poll sockets for pending data or await for space to write.

#### Available Components

- [Stream](https://github.com/icicleio/stream): Common coroutine-based interface for reading and writing data.
- [Socket](https://github.com/icicleio/socket): Asynchronous stream socket server and client.
- [Concurrent](https://github.com/icicleio/concurrent): Provides an easy to use interface for parallel execution with non-blocking communication and task execution (under development).
- [DNS](https://github.com/icicleio/dns): Asynchronous DNS resolver and connector.
- [HTTP](https://github.com/icicleio/http): Asynchronous HTTP server and client (under development).
- [React Adapter](https://github.com/icicleio/react-adapter): Adapts the event loop and awaitables of Icicle to interfaces compatible with components built for React.

##### Requirements

- PHP 5.5+

##### Installation

The recommended way to install Icicle is with the [Composer](http://getcomposer.org/) package manager. (See the [Composer installation guide](https://getcomposer.org/doc/00-intro.md) for information on installing and using Composer.)

Run the following command to use Icicle in your project: 

```bash
composer require icicleio/icicle
```

You can also manually edit `composer.json` to add Icicle as a project requirement.

```js
// composer.json
{
    "require": {
        "icicleio/icicle": "^0.9"
    }
}
```

##### Suggested

- [pcntl extension](http://php.net/manual/en/book.pcntl.php): Enables custom signal handling.
- [ev extension](https://pecl.php.net/package/ev): Allows for the most performant event loop implementation.
- [event extension](https://pecl.php.net/package/event): Another extension allowing for event loop implementation with better performance (ev extension preferred).
- [libevent extension](https://pecl.php.net/package/libevent): Similar to the event extension, it allows for event loop implementation with better performance (ev extension preferred).

#### Example

The example below uses the [HTTP component](https://github.com/icicleio/http) (under development) to create a simple HTTP server that responds with `Hello, world!` to every request.

```php
#!/usr/bin/env php
<?php

require '/vendor/autoload.php';

use Icicle\Http\Message\RequestInterface;
use Icicle\Http\Message\Response;
use Icicle\Http\Server\Server;
use Icicle\Loop;

$server = new Server(function (RequestInterface $request) {
    $response = new Response(200);
    yield $response->getBody()->end('Hello, world!');
    yield $response->withHeader('Content-Type', 'text/plain');
});

$server->listen(8080);

echo "Server running at http://127.0.0.1:8080\n";

Loop\run();
```

#### Documentation and Support

- [Full API Documentation](https://github.com/icicleio/icicle/wiki)
- [Official Twitter](https://twitter.com/icicleio)
- [Gitter Chat](https://gitter.im/icicleio/icicle)

## Awaitables

**[Awaitables API documentation](https://github.com/icicleio/icicle/wiki/Awaitables)**

Icicle implements awaitables based on the [Promises/A+](http://promisesaplus.com) specification, adding support for cancellation.

Awaitables are objects that act as placeholders for the future value of an asynchronous operation. Pending awaitables may either be fulfilled with any value (including other awaitables, `null`, and exceptions) or rejected with any value (non-exceptions are encapsulated in an exception). Once an awaitable is fulfilled or rejected (resolved) with a value, the awaitable cannot becoming pending and the resolution value cannot change.

**Awaitables are designed to be yielded in [coroutines](#coroutines), defining an interruption point where the coroutine is interrupted until the awaitable has been resolved.**

Callback functions are another way of accessing the resolution value of awaitables. Unlike other APIs that use callbacks, **awaitables provide an execution context to callback functions, allowing callbacks to return values and throw exceptions**.

All awaitable objects implement a common interface: `Icicle\Awaitable\Awaitable`. While the primary awaitable implementation is `Icicle\Awaitable\Promise`, several other classes also implement `Icicle\Awaitable\Awaitable`.

The `Icicle\Awaitable\Awaitable::then(callable $onFulfilled = null, callable $onRejected = null)` method is the primary way to register callbacks that receive either the value used to fulfill the awaitable or the exception used to reject the awaitable. An awaitable is returned by `then()`, which is resolved with the return value of a callback or rejected if a callback throws an exception.

The `Icicle\Awaitable\Awaitable::done(callable $onFulfilled = null, callable $onRejected = null)` method registers callbacks that should either consume resolution values or handle errors. No value is returned from `done()`. Values returned by callbacks registered using `done()` are ignored and exceptions thrown from callbacks are re-thrown in an uncatchable way.

*[More on using callbacks to interact with awaitables...](https://github.com/icicleio/icicle/wiki/Awaitables#interacting-with-awaitables)*

```php
use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Executor\Executor;
use Icicle\Dns\Resolver\Resolver;
use Icicle\Loop;
use Icicle\Socket\Client\ClientInterface;
use Icicle\Socket\Client\Connector;

$resolver = new Resolver(new Executor('8.8.8.8'));

// Method returning a Generator used to create a Coroutine (a type of awaitable)
$awaitable1 = new Coroutine($resolver->resolve('example.com'));

$awaitable2 = $awaitable1->then(
    function (array $ips) { // Called if $awaitable1 is fulfilled.
        $connector = new Connector();
        return new Coroutine($connector->connect($ips[0], 80)); // Return another awaitable.
        // $awaitable2 will adopt the state of the awaitable returned above.
    }
);

$awaitable2->done(
    function (ClientInterface $client) { // Called if $awaitable2 is fulfilled.
        echo "Asynchronously connected to example.com:80\n";
    },
    function (Exception $exception) { // Called if $awaitable1 or $awaitable2 is rejected.
        echo "Asynchronous task failed: {$exception->getMessage()}\n";
    }
);

Loop\run();
```

The example above uses the [DNS component](https://github.com/icicleio/Dns) to resolve the IP address for a domain, then connect to the resolved IP address. The `resolve()` method of `$resolver` and the `connect()` method of `$connector` both return generators that are used to create coroutines (a type of awaitable). `$awaitable1` created by `resolve()` will either be fulfilled or rejected:

- If `$awaitable1` is fulfilled, the callback function registered in the call to `$awaitable1->then()` is executed, using the fulfillment value of `$awaitable1` as the argument to the function. The callback function then returns the awaitable created from `connect()`. The resolution of `$awaitable2` will then be determined by the resolution of this returned awaitable (`$awaitable2` will adopt the state of the awaitable created from `connect()`).
- If `$awaitable1` is rejected, `$awaitable2` is rejected since no `$onRejected` callback was registered in the call to `$awaitable1->then()`

*[More on awaitable resolution and propagation...](https://github.com/icicleio/icicle/wiki/Awatiables#resolution-and-propagation)*

##### Brief overview of awaitable API features

- Asynchronous resolution (callbacks are not called before `then()` or `done()` return).
- Convenience methods for registering special callbacks to handle awaitable resolution.
- Lazy execution of awaitable-creating functions.
- Operations on collections of awaitables to join, select, iterate, and map to other awaitables or values.
- Support for cancellation.
- Methods to convert synchronous functions or callback-based functions into functions accepting and returning awaitables.

## Coroutines

**[Coroutine API documentation](https://github.com/icicleio/icicle/wiki/Coroutines)**

Coroutines are interruptible functions implemented using [Generators](http://www.php.net/manual/en/language.generators.overview.php). A `Generator` usually uses the `yield` keyword to yield a value from a set to implement an iterator. Coroutines use the `yield` keyword to define interruption points. When a coroutine yields a value, execution of the coroutine is temporarily interrupted, allowing other tasks to be run, such as I/O, timers, or other coroutines.

When a coroutine yields an [awaitable](#awaitables), execution of the coroutine is interrupted until the awaitable is resolved. If the awaitable is fulfilled with a value, the yield statement that yielded the awaitable will take on the resolved value. For example, `$value = (yield Icicle\Awaitable\resolve(2.718));` will set `$value` to `2.718` when execution of the coroutine is resumed. If the awaitable is rejected, the exception used to reject the awaitable will be thrown into the function at the yield statement. For example, `yield Icicle\Awaitable\reject(new Exception());` would behave identically to replacing the yield statement with `throw new Exception();`.

Note that **no callbacks need to be registered** with the awaitables yielded in a coroutine and **errors are reported using thrown exceptions**, which will bubble up to the calling context if uncaught in the same way exceptions bubble up in synchronous code.

The example below creates an `Icicle\Coroutine\Coroutine` instance from a function returning a `Generator`. (`Icicle\Dns\Connector\Connector` in the [DNS component](//github.com/icicleio/dns) uses a coroutine structured similarly to the one below, except it attempts to connect to other IPs returned from the resolver if the first one fails.)

```php
use Icicle\Coroutine\Coroutine;
use Icicle\Dns\Executor\Executor;
use Icicle\Dns\Resolver\Resolver;
use Icicle\Loop;
use Icicle\Socket\Client\Connector;

$generator = function () {
    try {
        $resolver = new Resolver(new Executor('8.8.8.8'));
        
        // This coroutine pauses until yielded coroutine is fulfilled or rejected.
        $ips = (yield $resolver->resolve('example.com'));
		
        $connector = new Connector();
        
        // This coroutine pauses again until yielded coroutine is fulfilled or rejected.
        $client = (yield $connector->connect($ips[0], 80));
		
        echo "Asynchronously connected to example.com:80\n";
    } catch (Exception $exception) {
        echo "Asynchronous task failed: {$exception->getMessage()}\n";
    }
};

$coroutine = new Coroutine($generator());

Loop\run();
```

The example above does the same thing as the example in the section on [awaitables](#awaitables) above, but instead uses a coroutine to **structure asynchronous code like synchronous code**. Fulfillment values of awaitables are accessed through simple variable assignments and exceptions used to reject awaitables are caught using a try/catch block, rather than creating and registering callback functions to each awaitable.

An `Icicle\Coroutine\Coroutine` object is also an [awaitable](#awaitables), implementing `Icicle\Awaitable\Awaitable`. The awaitable is fulfilled with the last value yielded from the generator (or fulfillment value of the last yielded awaitable) or rejected if an exception is thrown from the generator. **A coroutine may then yield other coroutines, suspending execution of the calling coroutine until the yielded coroutine has completed execution.** If a coroutine yields a `Generator`, it will automatically be converted to a `Icicle\Coroutine\Coroutine` and handled in the same way as a yielded awaitable.

## Loop

**[Loop API documentation](https://github.com/icicleio/icicle/wiki/Loop)**

The event loop schedules functions, runs timers, handles signals, and polls sockets for pending reads and available writes. There are several event loop implementations available depending on what PHP extensions are available. The `Icicle\Loop\SelectLoop` class uses only core PHP functions, so it will work on any PHP installation, but is not as performant as some of the other available implementations. All event loops implement `Icicle\Loop\Loop` and provide the same features.

The event loop should be accessed via functions defined in the `Icicle\Loop` namespace. If a program requires a specific or custom event loop implementation, `Icicle\Loop\loop()` can be called with an instance of `Icicle\Loop\Loop` before any other loop functions to use that instance as the event loop.

The `Icicle\Loop\run()` function runs the event loop and will not return until the event loop is stopped or no events are pending in the loop.

The following code demonstrates how timers can be created to execute functions after a number of seconds elapses using the `Icicle\Loop\timer()` function.

```php
use Icicle\Loop;

Loop\timer(1, function () { // Executed after 1 second.
	echo "First.\n";
	Loop\timer(1.5, function () { // Executed after 1.5 seconds.
	    echo "Second.\n";
	});
	echo "Third.\n";
	Loop\timer(0.5, function () { // Executed after 0.5 seconds.
		echo "Fourth.\n";
	});
	echo "Fifth.\n";
});

echo "Starting event loop.\n";
Loop\run();
```

The above code will output:

```
Starting event loop.
First.
Third.
Fifth.
Fourth.
Second.
```

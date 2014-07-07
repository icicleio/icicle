Icicle
======

Icicle is a library for writing asynchronous code in PHP to create web services.

Icicle uses [Promises](#promises) and [Coroutines](#coroutines) to allow asynchronous code to be written using techniques used to write synchronous code instead of the nested callbacks often seen in other asynchronous code.

Icicle also provides [Sockets](#sockets) for performing asynchronous network and file operations as well as promise-based [Streams](#streams) for reading and writing from sockets and manipulating data. An [event loop](#loop) is used to schedule functions, run timers, handle signals, and poll sockets for pending data or available writes.

## Promises

Icicle implements promises based on the JavaScript [Promises/A+](http://promisesaplus.com) specification. Promises may be fulfilled with any value (including null and Exceptions) and are rejected using Exceptions.

Promises provide a predictable execution context to callback functions, allowing callbacks to return values and throw Exceptions. The `then()` and `done()` methods of promises is used to define callbacks that receive either the value used to fulfill the promise or the Exception used to reject the promise. A promise instance is returned by `then()`, which is later fulfilled with the return value of a callback or rejected if a callback throws an Exception. The `done()` method is meant to define callbacks that consume promised values or handle errors. `done()` returns nothing - return values of callbacks defined using `done()` are ignored and Exceptions are thrown in an uncatchable way.

Calls to `then()` or `done()` do not need to define both callbacks. If the `$onFulfilled` or `$onRejected` callback are omitted from a call to `then()`, the returned promise is either fulfilled or rejected using the same value that was used to resolve the original promise. If omitting the `$onRejected` callback from a call to `done()`, you must be sure the promise cannot be rejected or the Exception used to reject the promise will be thrown in an uncatchable way.

```php
$promise1 = doSomethingAsynchronously(); // Returns a promise.

$promise2 = $promise1->then(
	function ($value) { // Called if $promise1 is fulfilled.
		if (null === $value) {
			throw new Exception("Invalid value!"); // Rejects $promise2.
		}
		// Do something with $value and return $newValue.
		return $newValue; // Fulfills $promise2 with $newValue;
	}
);

$promise2->done(
	function ($value) {
		echo "Asynchronous task resulted in value: {$value}\n";
	},
	function (Exception $exception) { // Called if $promise1 or $promise 2 is rejected.
		echo "Asynchronous task failed: {$exception->getMessage()}\n";
	}
)
```

If `$promise1` is fulfilled, the callback defined in the call to `then()` is called. If the value is `null`, `$promise2` is rejected with the Exception thrown in the defined callback. Otherwise `$value` is used, returning `$newValue`, which is used to fulfill `$promise2`. If `$promise1` is rejected, `$promise2` is rejected since no `$onRejected` callback was defined in the `then()` call on `$promise1`.

[Promise API documentation >>>](src/Promise)

## Coroutines

Coroutines are interruptible functions implemented using [Generators](http://www.php.net/manual/en/language.generators.overview.php) (PHP 5.5 required). Coroutines use [Promises](#promises) as interruption points via the `yield` keyword. Execution of the function is resumed once the yielded Promise is resolved. If the promise is fulfilled with a value, that yield statement which yielded the promise will take on that value. For example, `$value = (yield Promise::resolve(3.14));` will set `$value` to 3.14 when the promise resolves. If the promise is rejected, the Exception used to reject the promise will be thrown into the function at the yield statement. A Coroutine instance is itself a promise, which is fulfilled with the last value yielded from the Generator (or fulfillment value of the last yielded promise) or rejected if an exception is thrown from the Generator.

The example below uses the `call()` static method of the Coroutine class to create a Coroutine instance from a callable function returning a Generator.

```php
use Icicle\Coroutine\Coroutine;

$coroutine = Coroutine::call(function () {
	try {
		$value = (yield doSomethingAsynchronously());
		
		if (null === $value) {
			throw new Exception("Invalid value!");
		}
		
		// Do something with $value.
		
		echo "Asynchronous task resulted in value: {$value}\n";
		
	} catch (Exception $exception) {
		// Promise returned by doSomethingAsynchronously() was rejected or was fulfilled with null.
		echo "Asynchronous task failed: {$exception->getMessage()}\n";
	}
});
```

This example code does the same thing as the example shown in the section on promises above, but instead uses a Coroutine to write asynchronous code that looks like synchronous code using a try/catch block instead of defining multiple callback functions.

[Coroutine API documentation >>>](src/Coroutine)

## Loop

The event loop schedules functions, runs timers, handles signals, and polls sockets for pending reads and available writes. There are several event loop implementations available depending on what extensions are installed in PHP. `SelectLoop` only requires core PHP functions so it will work on any PHP installation, but is not as performant as some of the other available implementations. All implement `LoopInterface` and provide the same features.

The event loop should be accessed via the static methods of the `Loop` class.

[Loop API documentation >>>](src/Loop)

## Sockets

[Sockets API documentation >>>](src/Socket)

## Streams

[Streams API documentation >>>](src/Stream)

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

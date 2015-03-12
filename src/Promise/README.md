# Promises

Icicle implements promises based on the [Promises/A+](http://promisesaplus.com) specification, adding support for cancellation.

Promises are objects that act as placeholders for the future value of an asynchronous operation. Pending promises may either be fulfilled with any value (including other promises, `null`, and exceptions) or rejected with an exception. Once a promise is fulfilled or rejected (resolved) with a value, the promise cannot becoming pending and the resolution value cannot change.

Callback functions are the primary way of accessing the resolution value of promises. Unlike other APIs that use callbacks, **promises provide an execution context to callback functions, allowing callbacks to return values and throw exceptions**.

Callback functions registered to promises are always [invoked asynchronously](#asynchronous-callback-invocation) to ensure consistent behavior regardless of the state of the promise at the time callbacks are registered.

## Documentation

- [Creating a Promise](#creating-a-promise)
    - [Promise](#promise)
    - [Deferred](#deferred)
    - [LazyPromise](#lazypromise)
    - [resolve()](#resolve)
    - [reject()](#reject)
- [Interacting with Promises](#interacting-with-promises)
    - [PromiseInterface](#promiseinterface)
        - [then()](#then)
        - [done()](#done)
        - [cancel()](#cancel)
        - [timeout()](#timeout)
        - [delay()](#delay)
        - [capture()](#capture)
        - [always()](#always)
        - [after()](#after)
        - [tap()](#tap)
        - [cleanup()](#cleanup)
        - [isPending()](#ispending)
        - [isFulfilled()](#isfulfilled)
        - [isRejected()](#isrejected)
        - [getResult()](#getresult)
    - [Combining Promises](#combining-promises)
        - [settle()](#settle)
        - [join()](#join)
        - [any()](#any)
        - [some()](#some)
        - [choose()](#choose)
        - [map()](#map)
        - [reduce()](#reduce)
        - [iterate()](#iterate)
    - [Using Promises with Existing Functions](#using-promises-with-existing-functions)
        - [lift()](#lift)
        - [promisify()](#promisify)
- [Resolution and Propagation](#resolution-and-propagation)
    - [Child Promise Resolution](#child-promise-resolution)
    - [Asynchronous Callback Invocation](#asynchronous-callback-invocation)
    - [Promise Chaining](#promise-chaining)
    - [Error Handling](#error-handling)
    - [Iterative Resolution](#iterative-resolution)
- [Acknowledgements](#acknowledgements)

#### Function prototypes

Prototypes for object instance methods are described below using the following syntax:

``` php
ReturnType $classOrInterfaceName->methodName(ArgumentType $arg1, ArgumentType $arg2)
```

Prototypes for static methods are described below using the following syntax:

``` php
ReturnType ClassName::methodName(ArgumentType $arg1, ArgumentType $arg2)
```

To document the expected prototype of a callback function used as method arguments or return types, the documentation below uses the following syntax for `callable` types:

``` php
callable<ReturnType (ArgumentType $arg1, ArgumentType $arg2)>
```

## Creating a Promise

Promises can be created in a few different ways depending on your needs. All promises implement `Icicle\Promise\PromiseInterface`, which is described in the section on [interacting with promises](#interacting-with-promises).

### Promise

When a `Icicle\Promise\Promise` object is created, it invokes a resolver function given to the constructor with the following prototype: `callable<void (callable (void (mixed $value = null) $resolve, callable (void (Exception $exception) $reject>`. The resolver function initiates the (asynchronous) computation, calling the `$resolve($value = null)` function with the resolution value or `$reject(Exception $exception)` with an exception. An optional cancellation function with the prototype `callable<void Exception $exception>` can also be provided that is called if the promise is cancelled.

``` php
use Icicle\Promise\Promise;

$resolver = function ($resolve, $reject) {
    // Initiate asynchronous computation.
    // $resolve and $reject can be directly called or passed as callbacks to other functions.
    $resolve($result);
};

$onCancelled = function (Exception $exception) {
    // Perform any necessary cleanup.
};

$promise = new Promise($resolver, $onCancelled);
```

This may at first glance seem like an usual way to perform an operation and return a value. Remember that the code contained in the resolver function is not meant to be strictly synchronous code, but rather it is meant to perform an asynchronous operation and will likely define other callback functions, calling `$resolve` or `$reject` sometime after the resolver function has been executed. Remember that promises may also be resolved with other promises, causing the resolved promise to adopt the state of that promise (that is, passing a promise to `$resolve` will fulfill or reject the promise when the passed promise is fulfilled or rejected).

If the resolver function throws an exception, the promise is rejected with that exception.

##### Example

The following code creates a promise that is resolved when a connection is successfully made to a server. The `connect()` method of the `Icicle\Socket\Client` class in the [Socket](../Socket) component use a similar approach to establish connections asynchronously.

``` php
use Icicle\Loop\Loop;
use Icicle\Promise\Promise;

$promise = new Promise(
    function ($resolve, $reject) use (&$await) {
        $client = stream_socket_client(
            'tcp://8.8.8.8:53',
            $errno,
            $errstr,
            null,
            STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT
        );
        
        if (!$client || $errno) {
            throw new Exception('Could not connect to DNS server.'); // Rejects promise.
        }
        
        $await = Loop::await($client, function ($client, $expired) use (&$await, $resolve, $reject) {
            if ($expired) {
                $reject(new Exception('Connecting to the DNS server timed out.'));
            } else {
                $resolve($client);
            }
            $await->free();
        });
        
        $await->listen(10);
    },
    function (Exception $exception) use (&$await) {
        $await->free();
    }
);

// Use result of promise by calling $promise->then(), $promise->done(), etc.

Loop::run();
```

### Deferred

When a task is instigated in one piece of code and completed in another (e.g., separate methods of an object), a `Icicle\Promise\Deferred` object can be used to encapsulate a promise and control the state of that promise externally. A `Icicle\Promise\Deferred` object is designed to be kept private by the code that wishes to control the state of the promise (e.g., a class), while being able to provide the promise to consuming code through the `getPromise()` method. A cancellation function may optionally be provided to the constructor when creating a `Icicle\Promise\Deferred` object that is called if the encapsulated promise is cancelled.

``` php
use Icicle\Promise\Deferred;

$onCancelled = function (Exception $exception) {
    // Perform any necessary cleanup.
};

$deferred = new Deferred($onCancelled);

$promise = $deferred->getPromise();
```

`Icicle\Promise\Deferred` objects have only three methods other than the constructor:
- `void Deferred->resolve(mixed $value = null)`: Resolves the encapsulated promise with the given value or promise.
- `void Deferred->reject(Exception $exception)`: Rejects the encapsulated promise with the given exception.
- `PromiseInterface Deferred->getPromise()`: Returns the encapsulated promise so it can be given to consumers.

Other objects can be created that can act like a deferred by implementing `Icicle\Promise\PromisorInterface`.

### LazyPromise

A `Icicle\Promise\LazyPromise` object is constructed by providing the constructor with a function that creates a promise that is only called once the result of the promise is requested. That is, the function creating the promise is not called until a callback using the resolution value of the promise is registered using `then()`, `done()`, etc. Lazy promises provide an easy way to perform operations only as needed for a computation. `Icicle\Promise\LazyPromise` implements `Icicle\Promise\PromiseInterface`, therefore they can be treated like any other promise.

``` php
use Icicle\Promise\LazyPromise;

$promisor = function () {
    $promise = doSomethingAsynchronously(); // Function returning a promise.
    return $promise->then(function ($result) {
        // Use result to perform another computation.
    });
};

$lazy = new LazyPromise($promisor); // $promiser will not be called at this point.

// Other code...

// $promisor called only when a callback is registered to the promise.
$lazy->done(
    function ($result) {
        // Use $result.
    },
    function (Exception $e) {
        // Handle exception.
    }
);
```

### resolve()

``` php
PromiseInterface Promise::resolve($value = null)
```

This static method returns a fulfilled promise using the given value. There are two possible outcomes depending on the type of the passed value: (1) `Icicle\Promise\PromiseInterface`: The promise is returned without modification. (2) All other types: A fulfilled promise is returned using the given value as the result.

### reject()

``` php
PromiseInterface Promise::reject(Exception $exception)
```

This static method returns a rejected promise using the given exception as the rejection reason.

## Interacting with Promises

### PromiseInterface

All promise objects implement `Icicle\Promise\PromiseInterface`, which provides a variety of functions for registering callbacks to receive the resolution value of a promise. While the primary promise implementation is `Icicle\Promise\Promise`, several other classes in this component also implement `Icicle\Promise\PromiseInterface`.

#### then()

``` php
PromiseInterface $promiseInterface->then(
    callable<mixed (mixed $value)> $onFulfilled = null,
    callable<mixed (Exception $exception)> $onRejected = null
)
```

This method is the primary way to register callbacks that receive either the value used to fulfill the promise or the exception used to reject the promise. Another `Icicle\Promise\PromiseInterface` object is returned by this method, which is resolved with the return value of a callback or rejected if a callback throws an exception. For more on how promises are resolved by callbacks, see the section on [Resolution and Propagation](#resolution-and-propagation).

---

#### done()

``` php
void $promiseInterface->done(
    callable<void (mixed $value)> $onFulfilled = null,
    callable<void (Exception $exception)> $onRejected = null
)
```

This method registers callbacks that should either consume promised values or handle errors. No value is returned from `done()`. Values returned by callbacks registered using this method are ignored and exceptions thrown from callbacks are re-thrown in an *uncatchable* way.

---

#### cancel()

``` php
void $promiseInterface->cancel(Exception $exception = null)
```

Cancels the promise with the given exception (`Icicle\Promise\Exception\CancelledException` used if no exception is provided). Canceling a promise rejects the promise with the given exception and calls the cancellation callback if one was provided when the promise was created. The parent promise is also cancelled if no other children of that parent have been created.

---

#### timeout()

``` php
PromiseInterface $promiseInterface->timeout(float $timeout, Exception $exception = null)
```

Returns a promise that is rejected in `$timeout` seconds with the given exception (or `Icicle\Promise\Exception\TimeoutException` if no exception is provided) if the promise is not resolved before that time. When the promise resolves, the returned promise is fulfilled or rejected with the same value.

---

#### delay()

``` php
PromiseInterface $promiseInterface->delay(float $time)
```

Returns a promise that is fulfilled $time seconds after this promise is fulfilled. If the promise is rejected, the returned promise is immediately rejected.

---

#### capture()

``` php
PromiseInterface $promiseInterface->capture(callable<mixed (Exception $exception)> $onRejected)
```

Assigns a callback function that is called when the promise is rejected. If a type-hint is defined on the callable (e.g.: `function (RuntimeException $exception) { /* ... */ }`, then the function will only be called if the exception is an instance of the type-hinted exception.

##### Example

``` php
$promise2 = $promise1->capture(function (RuntimeException $exception) {
    // This function is only called if $promise1 is rejected with an instance of RuntimeException.
    // Otherwise $promise2 is rejected with the same exception as $promise1.
});
```

---

#### always()

``` php
PromiseInterface $promiseInterface->always(callable<mixed (mixed $value)> $onResolved)
```

Assigns a callback function to be called if the promise is fulfilled or rejected. Shortcut to calling `then($onResolved, $onResolved)`.

---

#### after()

``` php
void $promiseInterface->after(callable<void (mixed $value)> $onResolved)
```

Assigns a callback to be called if the promise is fulfilled or rejected. Shortcut to calling `done($onResolved, $onResolved)`. Note that like `done()`, returned values are ignored and thrown exceptions are re-thrown in an uncatchable way.

---

#### tap()

``` php
PromiseInterface $promiseInterface->tap(callable<void (mixed $value)> $onFulfilled)
```

Calls the given function with the value used to fulfill the promise, then fulfills the returned promise with the same value. If the promise is rejected, the returned promise is also rejected and $onFulfilled is not called. If `$onFulfilled` throws an exception, the returned promise is rejected with the thrown exception. The return value of `$onFulfilled` is not used.

---

#### cleanup()

``` php
PromiseInterface $promiseInterface->cleanup(callable<void function (mixed $value)> $onFulfilled)
```

The callback given to this function will be called if the promise is fulfilled or rejected. The callback is called with no arguments. If the callback does not throw, the returned promise is resolved in the same way as the original promise. That is, it is fulfilled or rejected with the same value or exception. If `$onFulfilled` throws an exception, the returned promise is rejected with the thrown exception.

---

#### isPending()

``` php
bool $promiseInterface->isPending()
```

Determines if the promise has been resolved.

---

#### isFulfilled()

``` php
bool $promiseInterface->isFulfilled()
```

Determines if the promise has been fulfilled.

---

#### isRejected()

``` php
bool $promiseInterface->isRejected()
```

Determines if the promise has been rejected.

---

#### getResult()

``` php
mixed $promiseInterface->getResult()
```

Returns the value of a fulfilled or rejected promise if it has been resolved, otherwise it throws `Icicle\Promise\Exception\UnresolvedException`. Note that this function generally should not be used to access the resolution value of a promise, but is provided for testing or unusual situations.

### Combining Promises

The `Icicle\Promise\Promise` class contains several static methods performing operations on sets of promises. All methods in this section are designed so most of their parameters may either be promises or values (or an array containing any combination of promises and values). `Icicle\Promise\Promise::resolve()` is used on all values to create promises.

#### settle()

``` php
PromiseInterface Promise::settle(mixed[] $promises)
```

Returns a promise that is resolved when all promises are resolved. The returned promise will not reject by itself (only if cancelled). Returned promise is fulfilled with an array of resolved promises, with keys identical and corresponding to the original given array. The `$promises` array may contain any combination of promises or values.

---

#### join()

``` php
PromiseInterface Promise::join(mixed[] $promises)
```

Returns a promise that is fulfilled when all promises are fulfilled, and rejected if any promise is rejected. Returned promise is fulfilled with an array of values used to fulfill each contained promise, with keys corresponding to the array of promises or values. The `$promises` array may contain any combination of promises or values.

---

#### any()

``` php
PromiseInterface Promise::any(mixed[] $promises)
```

Returns a promise that is fulfilled when any promise is fulfilled, and rejected only if all promises are rejected. The `$promises` array may contain any combination of promises or values.

---

#### some()

``` php
PromiseInterface Promise::some(mixed[] $promises, int $required)
```

Returns a promise that is fulfilled when $required number of promises are fulfilled. The promise is rejected if `$required` number of promises can no longer be fulfilled. The `$promises` array may contain any combination of promises or values.

---

#### choose()

``` php
PromiseInterface Promise::choose(mixed[] $promises)
```

Returns a promise that is fulfilled or rejected when the first promise is fulfilled or rejected. The `$promises` array may contain any combination of promises or values.

---

#### map()

``` php
PromiseInterface[] Promise::map(mixed[] $promises, callable<mixed (mixed $value)> $callback)[]
```

Maps the callback to each promise as it is fulfilled. Returns an array of promises resolved by the return callback value of the callback function. The callback may return promises or throw exceptions to reject promises in the array. If a promise in the passed array rejects, the callback will not be called and the promise in the array is rejected for the same reason. The `$promises` array may contain any combination of promises or values. Tip: Use the `join()` or `settle()` method to determine when all promises in the array have been resolved.

---

#### reduce()

``` php
PromiseInterface Promise::reduce(
    mixed[] $promises,
    callable<mixed (mixed $carry)> $callback,
    mixed $initial = null
)
```

Reduce function similar to `array_reduce()`, only it works on promises and/or values. The `$promises` array may contain any combination of promises or values. The callback function may return a promise or value and `$initial` value may also be a promise or value.

---

#### iterate()

``` php
PromiseInterface Promise::iterate(
    callable<mixed (mixed $carry)> $worker,
    callable<bool (mixed $carry)> $predicate,
    mixed $seed = null
)
```

Calls `$worker` using the return value of the previous call until `$predicate` returns true. $seed is used as the initial parameter to `$worker`. `$predicate` is called before `$worker` with the value to be passed to `$worker`. If `$worker` or `$predicate` throws an exception, the promise is rejected using that exception. The call stack is cleared before each call to `$worker` to avoid filling the call stack. If `$worker` returns a promise, iteration waits for the returned promise to be resolved. `$seed` may be a promise or value.

### Using Promises with Existing Functions

The `Promise` class also contains two static methods for transforming a functions into a function that is able to take promises as arguments and return promises instead of values or throwing exceptions. 

#### lift()

``` php
callable<PromiseInterface (mixed ...$args)> Promise::lift(callable<mixed (mixed ...$args)> $worker)
```

Wraps the given callable `$worker` in a promise aware function that takes the same number of arguments as `$worker`, but those arguments may be promises for the future argument value or just values. The returned function will return a promise for the return value of `$worker` and will never throw. The `$worker` function will not be called until each promise given as an argument is fulfilled. If any promise provided as an argument rejects, the promise returned by the returned function will be rejected for the same reason. The promise is fulfilled with the return value of `$worker` or rejected if `$worker` throws.

---

#### promisify()

``` php
callable<PromiseInterface (mixed ...$args)> Promise::promisify(
    callable<mixed (mixed ...$args)> $worker,
    int $index = 0
)
```

Transforms a function `$worker` that takes a callback into a function that returns a promise. The promise is fulfilled with an 
array of the parameters that would have been passed to the callback function. The function returned from this method takes the same arguments as `$worker` except for the callback function, which is replaced by this function.

## Resolution and Propagation

### Child Promise Resolution

When a promise is resolved with a value (or fulfilled), each callback registered to receive the promise fulfillment value is invoked. Similarly, when a promise is rejected with an exception, each callback registered to receive the promise rejection reason is invoked.

When a callback is registered using a method that returns another promise (e.g. `then()`, `always()`, `capture()`, `timeout()`, `delay()`, `tap()`, and `cleanup()`), the return value of the callback is used to fulfill that promise, or if an exception is thrown, reject that promise.

``` php
$promise2 = $promise1->then(
    function ($value) {
        if (null === $value) {
            throw new Exception('Value cannot be null.'); // Throwing rejects $promise2 with the exception.
        }
        return $value + 1; // Returning a value will fulfill $promise2 with that value.
    },
    function (Exception $exception) {
        return 1; // Returning from the rejected handler fulfills $promise2 with that value.
    }
);
```

If a callback is omitted when calling `then()`, the returned promise is then fulfilled or rejected using the same value or exception as the parent promise. The example below is similar to the example above, except the `$onRejected` parameter of `then()` is now `null`. If `$promise1` is rejected, `$promise2` is also rejected with the same exception.

``` php
$promise2 = $promise1->then(
    function ($value) {
        if (null === $value) {
            throw new Exception('Value cannot be null.'); // Throwing rejects $promise2 with the exception.
        }
        return $value + 1; // Returning a value will fulfill $promise2 with that value.
    }
    // No $onRejected callback given, so if $promise1 rejects, $promise2 will automatically be rejected
    // with the same exception as $promise1.
);
```

Similarly, if no `$onFulfilled` callback is given, `$promise2` is fulfilled with the same value as `$promise1` if `$promise1` is fulfilled.

``` php
$promise2 = $promise1->then(
    null, // No $onFulfilled callback given, so if $promise1 is fulfilled, $promise2 is fulfilled with
          // the same value as $promise1.
    function (Exception $exception) {
        return 1; // Returning from the rejected handler fulfills $promise2 with that value.
    }
);
```

### Asynchronous Callback Invocation

Invocation of callbacks registered to a promise is guaranteed to be asynchronous. This means that registered callbacks will not be invoked until after `then()`, `done()` have returned and execution has left the current scope (i.e., the calling function returns). To make this clearer, consider the example below.

``` php
$promise->then(function ($value) {
    echo "{1}";
});
echo "{2}";
```

If callbacks were invoked immediately on registration if a promise was resolved, the output of the above code would depend on the state of `$promise`. If the promise was fulfilled, `{1}{2}` would be echoed. If the promise was pending, `{2}{1}` would be output.

While this example is contrived, this behavior can have significant consequences when working with objects or referenced variables. To ensure consistent behavior, callbacks registered to promises are *always* invoked asynchronously.

### Promise Chaining

``` php
use Icicle\Loop\Loop;
use Icicle\Promise\Deferred;

$deferred = new Deferred();

$deferred->getPromise()
    ->then(function ($value) {
        $value = (int) $value;
        if (0 === $value) {
            throw new RuntimeException('Value cannot be 0.');
        }
        return 100 / $value;
    })
    ->then(function ($value) {
        return $value * $value;
    })
    ->capture(function (RuntimeException $e) {
        return 0; // Analogous to a try/catch block.
    })
    ->done(
        function ($value) {
            echo "Result: {$value}\n";
        },
        function (Exception $e) {
            echo "Error: {$e->getMessage()}\n";
        }
    );

$deferred->resolve(0); // Echos "Result: 0"

Loop::run();
```
In the example above, resolving the promise with `0` causes the first callback to throw an exception. This exception is used to reject the returned promise. No rejection callback was registered on the promise returned from the first call to `then()`, so that promise is automatically rejected with the same exception. The promise returned from the second call to `then()` had a rejection callback registered using `capture()` with a type-hint of `RuntimeException`, which matches the type of the thrown exception, so the callback is invoked. That callback returns `0`, fulfilling the promise returned from `capture()` with that value. The promise returned from `capture()` had a fulfillment and rejection callback registered with `done()`. Since the promise was resolved with `0`, the fulfillment callback is invoked, echoing `Result: 0`.

##### Another Example

``` php
use Icicle\Loop\Loop;

$promise1 = doAsynchronousTask(); // Function returning a promise.

$promise2 = $promise1->then(
    function ($value) { // Called if $promise1 is fulfilled.
        if (null === $value) {
            throw new Exception("Invalid value!"); // Rejects $promise2.
        }
		
		return anotherAsynchronousTask($value); // Another function returning a promise.
		// $promise2 will adopt the state of the promise returned above.
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

In the above example, the functions `doAsynchronousTask()` and `anotherAsynchronousTask()` both return promises. `$promise1` created by `doAsynchronousTask()` will either be fulfilled or rejected:

- If `$promise1` is fulfilled, the callback function registered in the call to `$promise1->then()` is executed. If `$value` (the resolution value of `$promise1`) is `null`, `$promise2` is rejected with the exception thrown in the callback. Otherwise `$value` is used as a parameter to `anotherAsynchronousTask()`, which returns a new promise. The resolution of `$promise2` will then be determined by the resolution of this promise (`$promise2` will adopt the state of the promise returned by `anotherAsynchronousTask()`).
- If `$promise1` is rejected, `$promise2` is rejected since no `$onRejected` callback was registered in the call to `$promise1->then()`.

### Error Handling

When a promise is rejected, the exception used to reject the promise is not thrown, it is only given to callbacks registered using the methods described above. However, if `done()` is called without an `$onRejected` callback, the exception will be re-thrown in an uncatchable way (see the [Loop](../Loop) component for more on uncatchable exceptions).

Error handling with promises comes down to a simple rule: Call `done()` on the promise to consume the final result or handle any exceptions, or return the promise to the caller, thereby delegating error handling to the code requesting the promise resolution value.

### Iterative Resolution

Promise resolution is handled iteratively, so there is no concern of overflowing the call stack regardless of how deep the chain may have become. The example below demonstrates how a chain of 100 promises maintains a constant call stack size when the registered callbacks are invoked.

``` php
use Icicle\Loop\Loop;
use Icicle\Promise\Deferred;

$deferred = new Deferred();
$promise = $deferred->getPromise();

for ($i = 0; $i < 100; ++$i) {
    $promise = $promise->then(function ($value) {
        printf("%3d) %d\n", $value, xdebug_get_stack_depth()); // Stack size is constant
        return ++$value;
    });
}

$deferred->resolve(1);

Loop::run();
```

When a promise is resolved with another promise the original promise transfers the responsibility of invoking registered callbacks to the promise used for resolution. A promise may be fulfilled any number of times with another promise, and the call stack will not overflow when the promise is eventually resolved.

## Cancellation

If a promise is still pending, the promise may be cancelled using the `cancel()` method ([see prototype for more information](#promiseinterface-cancel)). This immediately rejects the promise, and calls any cancellation callback that may have been provided when the promise was created.

When cancelling a child promise (a promise returned by `then()` or other methods returning another promise), the parent promise is also cancelled if there are no other pending children. The parent process is only cancelled if all children are also cancelled.

``` php
$parent = new Promise(function ($resolve, $reject) { /* ... */ });

$child1 = $parent->then();
$child2 = $parent->then();

$child1->cancel(); // Cancels only $child1.

$child2->cancel(); // Cancels both $child2 and $parent.
```

## Acknowledgements

The behavior and interface of this class was inspired by the [when.js](https://github.com/cujojs/when) promise implementation for JavaScript.


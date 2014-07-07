Promises
======

Icicle implements promises based on the JavaScript [Promises/A+](http://promisesaplus.com) specification. Promises may be fulfilled with any value (including null and Exception instances) and are rejected using Exceptions.

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
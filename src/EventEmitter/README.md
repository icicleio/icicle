# Event Emitter

Event emitters can create a set of events identified by an integer or string to which other code can register callbacks that are invoked when the event occurs. Each event emitter should implement `Icicle\EventEmitter\EventEmitterInterface`, which can be done easily by using `Icicle\EventEmitter\EventEmitterTrait` in the class definition.

This implementation differs from other event emitter libraries by ensuring that a particular callback can only be registered once for a particular event identifier. An attempt to register a previously registered callback is a no-op.

Event identifiers are also strictly enforced to aid in debugging. Event emitter objects must initial event identifiers of events they wish to emit. If an attempt to register a callback is made on a non-existent event, a `Icicle\EventEmitter\Exception\InvalidEventException` is thrown.

##### Example

```php
use Icicle\EventEmitter\EventEmitterInterface;
use Icicle\EventEmitter\EventEmitterTrait;

class ExampleEventEmitter implements EventEmitterInterface
{
    use EventEmitterTrait;
    
    public function __construct()
    {
        $this->createEvent('action'); // Creates event with 'action' identifier.
    }
    
    public function action($arg1, $arg2)
    {
        $this->emit('action', $arg1, $arg2); // Emits an event with 'action' identifier.
    }
}
```
The example class above implements `Icicle\EventEmitter\EventEmitterInterface` so it can emit events to a set of listeners. The example below demonstrates how listeners can be added to an instance of this class and the behavior of emitting events. This class will also be used in several other examples below.

```php
$emitter = new ExampleEventEmitter();

// Registers a callback to be called each time the event is emitted.
$emitter->on('action', function ($arg1, $arg2) {
    echo "Argument 1 value: {$arg1}\n";
    echo "Argument 2 value: {$arg2}\n";
});

// Registers a callback to be called only the next time the event is emitted.
$emitter->once('action', function ($arg1, $arg2) {
    $result = $arg1 * $arg2;
    echo "Result: {$result}\n";
});

$emitter->action(404, 3.14159); // Calls both functions above.
$emitter->action(200, 2.71828); // Calls only the first function.
```

## Documentation

- [EventEmitterInterface](#eventemitterinterface)
    - [addListener()](#addlistener) - Adds an event listener.
    - [on()](#on) - Adds an event listener called each time an event is emitted.
    - [once()](#once) - Adds an event listener called only the next time an event is emitted.
    - [removeListener()](#removeListener) - Removes an event listener.
    - [off()](#off) - Removes an event listener.
    - [removeAllListeners()](#removealllisteners) - Removes all listeners from an identifier or all identifiers.
    - [getListeners()](#getlisteners) - Returns the set of listeners for an event identifier.
    - [getListenerCount()](#getlistenercount) - Returns the number of listeners on an event identifier.
    - [emit()](#emit) - Emit an event.
- [EventEmitterTrait](#eventemittertrait)
    - [createEvent()](#createevent) - Creates an event identifier.
- [Using Promises with Event Emitters](#using-promises-with-event-emitters)
- [Using Coroutines with Event Emitters](#using-coroutines-with-event-emitters)

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

## EventEmitterInterface

`Icicle\EventEmitter\EventEmitterInterface` is an interface that any class can implement for emitting events. The simplest way to implement this interface is to use `Icicle\EventEmitter\EventEmitterTrait` in the class definition or for the class to extend `Icicle\EventEmitter\EventEmitter`.

#### addListener()

```php
$this $eventListenerInterface->addListener(
    string|int $event,
    callable<void (mixed ...$args)> $callback,
    bool $once = false
)
```

Adds an event listener defined by `$callback` to the event identifier `$event`. If `$once` is `true`, the listener will only be called the next time the event is emitted, otherwise the listener will be called each time the event is emitted. If the identifier given by `$event` does not exist, an `Icicle\EventEmitter\Exception\InvalidEventException` will be thrown.

---

#### on()

```php
$this $eventListenerInterface->on(string|int $event, callable<void (mixed ...$args)> $callback)
```

Adds an event listener defined by `$callback` to the event identifier `$event` that will be called each time the event is emitted. This method is identical to calling `addListener()` with `$once` as `false`. If the identifier given by `$event` does not exist, an `Icicle\EventEmitter\Exception\InvalidEventException` will be thrown.

---

#### once()

```php
$this $eventListenerInterface->once(string|int $event, callable<void (mixed ...$args)> $callback)
```

Adds an event listener defined by `$callback` to the event identifier `$event` that will be only the next time the event is emitted. This method is identical to calling `addListener()` with `$once` as `true`. If the identifier given by `$event` does not exist, an `Icicle\EventEmitter\Exception\InvalidEventException` will be thrown.

---

#### removeListener()

```php
$this $eventListenerInterface->removeListener(string|int $event, callable<void (mixed ...$args)> $callback)
```

Removes the event listener defined by `$callback` from the event identifier `$event`. This method will remove the listener regardless of if the listener was to be called each time the event was emitted or only the next time the event was emitted. If the was not a registered on the given event, this function is a no-op. If the identifier given by `$event` does not exist, an `Icicle\EventEmitter\Exception\InvalidEventException` will be thrown.

---

#### off()

```php
$this $eventListenerInterface->off(string|int $event, callable<void (mixed ...$args)> $callback)
```

This method is an alias of `removeListener()`.

---

#### removeAllListeners()

```php
$this $eventListenerInterface->removeAllListeners(string|int|null $event = null)
```

Removes all listeners from the event identifier or if `$event` is `null`, removes all listeners from all events. If the identifier given by `$event` does not exist, an `Icicle\EventEmitter\Exception\InvalidEventException` will be thrown.

---

#### getListeners()

```php
callable[] $eventListenerInterface->getListeners(string|int $event)
```

Returns all listeners on the event identifier as an array of callables. If the identifier given by `$event` does not exist, an `Icicle\EventEmitter\Exception\InvalidEventException` will be thrown.

---

#### getListenerCount()

```php
int $eventListenerInterface->getListenerCount(string|int $event)
```

Gets the number of listeners on the event identifier. If the identifier given by `$event` does not exist, an `Icicle\EventEmitter\Exception\InvalidEventException` will be thrown.

---

#### emit()

```php
bool $eventListenerInterface->emit(string|int $event, mixed ...$args)
```

Emits an event with the event identifier `$event`, passing the remaining arguments given to this function as the arguments to each event listener. The method returns `true` if any event listeners were invoked, `false` if none were. If the identifier given by `$event` does not exist, an `Icicle\EventEmitter\Exception\InvalidEventException` will be thrown.

## EventEmitterTrait

`Icicle\EventEmitter\EventEmitterTrait` is a simple way for any class to implement `Icicle\EventEmitter\EventEmitterInterface`. This trait defines a protected method that is not part of `Icicle\EventEmitter\EventEmitterInterface` that is used to create an event identifier.

#### createEvent()

```php
$this $eventEmitterTrait->create(string|int $identifier)
```

This method creates an event identifier so events may be emitted and listeners added. Generally this method will be called in the constructor to initialize a set of event identifiers.

## Using Promises with Event Emitters

The static method `Icicle\Promise\Promise::promisify()` can be used to create a function returning a promise that is resolved the next time an event emitter emits an event.

```php
use Icicle\Loop\Loop;
use Icicle\Promise\Promise;

// include ExampleEventEmitter class definition from above.

$emitter = new ExampleEventEmitter();

// Use once() since promises can only be resolved once.
$promisor = Promise::promisify([$emitter, 'once'], 1);

$promise = $promisor('action'); // Promise for 'action' event.

$promise = $promise->then(function (array $args) {
    list($arg1, $arg2) = $args;
    echo "Argument 1 value: {$arg1}\n";
    echo "Argument 2 value: {$arg2}\n";
});

$emitter->action(404, 3.14159); // Fulfills promise.

Loop::run();
```

See the [Promise API documentation](../Promise) for more information on using promises.

## Using Coroutines with Event Emitters

Event emitters can be used to create and execute coroutines each time an event is emitted. The static method `Icicle\Coroutine\Coroutine::async()` returns a function that can be used as the event listener on an event emitter.

```php
use Icicle\Coroutine\Coroutine;
use Icicle\Loop\Loop;

// include ExampleEventEmitter class definition from above.

$emitter = new ExampleEventEmitter();

$emitter->on('action', Coroutine::async(function ($arg1, $arg2) {
    $result = (yield $arg1 * $arg2);
    echo "Result: {$result}\n";
});

Loop::run();
```

See the [Coroutine API documentation](../Coroutine) for more information on using coroutines.

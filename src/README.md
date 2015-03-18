# Icicle

**Icicle is a PHP library for writing *asynchronous* code using *synchronous* coding techniques.**

Icicle uses [Coroutines](Coroutine) built with [Promises](Promise) to facilitate writing asynchronous code using techniques normally used to write synchronous code, such as returning values and throwing exceptions, instead of using nested callbacks typically found in asynchronous code.

#### Library Constructs

- [Coroutines](Coroutine): Interruptible functions for building asynchronous code using synchronous coding patterns and error handling.
- [Promises](Promise): Placeholders for future values of asynchronous operations. Callbacks registered with promises may return values and throw exceptions.
- [Loop (event loop)](Loop): Used to schedule functions, run timers, handle signals, and poll sockets for pending data or await for space to write.
- [Streams](Stream): Common interface for reading and writing data.
- [Sockets](Socket): Implement asynchronous network and file operations.
- [Event Emitters](EventEmitter): Allows objects to emit events that execute a set of registered callbacks.

**Please see the [main documentation](//github.com/icicleio/Icicle) for installation, requirements, and usage.**

# Icicle

**Icicle is a PHP library for writing *asynchronous* code using *synchronous* coding techniques.**

Icicle uses [Coroutines](#coroutines) built with [Promises](#promises) to facilitate writing asynchronous code using techniques normally used to write synchronous code, such as returning values and throwing exceptions, instead of using nested callbacks typically found in asynchronous code.

#### Library Constructs

- [Coroutines](#coroutines): Interruptible functions for building asynchronous code using synchronous coding patterns and error handling.
- [Promises](#promises): Placeholders for future values of asynchronous operations. Callbacks registered with promises may return values and throw exceptions.
- [Loop (event loop)](#loop): Used to schedule functions, run timers, handle signals, and poll sockets for pending data or await for space to write.
- [Streams](#streams): Common interface for reading and writing data.
- [Sockets](#sockets): Implement asynchronous network and file operations.
- [Event Emitters](#event-emitters): Allows objects to emit events that execute a set of registered callbacks.

**Please see the [main documentation] for installation, requirements, and usage.**

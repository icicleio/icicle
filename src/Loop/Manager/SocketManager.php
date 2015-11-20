<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager;

use Icicle\Loop\Events\SocketEvent;

interface SocketManager extends EventManager
{
    /**
     * Returns a SocketEvent object for the given stream socket resource.
     *
     * @param resource $resource
     * @param callable $callback
     *
     * @return \Icicle\Loop\Events\SocketEvent
     */
    public function create($resource, callable $callback): SocketEvent;
    
    /**
     * @param \Icicle\Loop\Events\SocketEvent $event
     * @param float|int $timeout
     */
    public function listen(SocketEvent $event, float $timeout = 0);
    
    /**
     * Cancels the given socket operation.
     *
     * @param \Icicle\Loop\Events\SocketEvent $event
     */
    public function cancel(SocketEvent $event);
    
    /**
     * Determines if the socket event is enabled (listening for data or space to write).
     *
     * @param \Icicle\Loop\Events\SocketEvent $event
     *
     * @return bool
     */
    public function isPending(SocketEvent $event): bool;
    
    /**
     * Frees the given socket event.
     *
     * @param \Icicle\Loop\Events\SocketEvent $event
     */
    public function free(SocketEvent $event);
    
    /**
     * Determines if the socket event has been freed.
     *
     * @param \Icicle\Loop\Events\SocketEvent $event
     *
     * @return bool
     */
    public function isFreed(SocketEvent $event): bool;

    /**
     * Unreferences the given socket event, that is, if the event is pending in the loop, the loop should not continue
     * running.
     *
     * @param \Icicle\Loop\Events\SocketEvent $event
     */
    public function unreference(SocketEvent $event);

    /**
     * References a socket event if it was previously unreferenced. That is, if the event is pending the loop will
     * continue running.
     *
     * @param \Icicle\Loop\Events\SocketEvent $event
     */
    public function reference(SocketEvent $event);
}

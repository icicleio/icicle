<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager;

use Icicle\Loop\Events\SocketEventInterface;

interface SocketManagerInterface
{
    /**
     * Returns a SocketEventInterface object for the given stream socket resource.
     *
     * @param resource $resource
     * @param callable $callback
     *
     * @return \Icicle\Loop\Events\SocketEventInterface
     */
    public function create($resource, callable $callback): SocketEventInterface;
    
    /**
     * @param \Icicle\Loop\Events\SocketEventInterface $event
     * @param float|int $timeout
     */
    public function listen(SocketEventInterface $event, float $timeout = 0);
    
    /**
     * Cancels the given socket operation.
     *
     * @param \Icicle\Loop\Events\SocketEventInterface $event
     */
    public function cancel(SocketEventInterface $event);
    
    /**
     * Determines if the socket event is enabled (listening for data or space to write).
     *
     * @param \Icicle\Loop\Events\SocketEventInterface $event
     *
     * @return bool
     */
    public function isPending(SocketEventInterface $event): bool;
    
    /**
     * Frees the given socket event.
     *
     * @param \Icicle\Loop\Events\SocketEventInterface $event
     */
    public function free(SocketEventInterface $event);
    
    /**
     * Determines if the socket event has been freed.
     *
     * @param \Icicle\Loop\Events\SocketEventInterface $event
     *
     * @return bool
     */
    public function isFreed(SocketEventInterface $event): bool;

    /**
     * Determines if any socket events are pending in the manager.
     *
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * Clears all socket events from the manager.
     */
    public function clear();
}

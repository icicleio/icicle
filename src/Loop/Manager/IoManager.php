<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager;

use Icicle\Loop\Events\Io;

interface IoManager extends EventManager
{
    /**
     * Returns a Io object for the given stream socket resource.
     *
     * @param resource $resource
     * @param callable $callback
     *
     * @return \Icicle\Loop\Events\Io
     */
    public function create($resource, callable $callback): Io;
    
    /**
     * @param \Icicle\Loop\Events\Io $event
     * @param float|int $timeout
     */
    public function listen(Io $event, float $timeout = 0);
    
    /**
     * Cancels the given socket operation.
     *
     * @param \Icicle\Loop\Events\Io $event
     */
    public function cancel(Io $event);
    
    /**
     * Determines if the socket event is enabled (listening for data or space to write).
     *
     * @param \Icicle\Loop\Events\Io $event
     *
     * @return bool
     */
    public function isPending(Io $event): bool;
    
    /**
     * Frees the given socket event.
     *
     * @param \Icicle\Loop\Events\Io $event
     */
    public function free(Io $event);
    
    /**
     * Determines if the socket event has been freed.
     *
     * @param \Icicle\Loop\Events\Io $event
     *
     * @return bool
     */
    public function isFreed(Io $event): bool;

    /**
     * Unreferences the given socket event, that is, if the event is pending in the loop, the loop should not continue
     * running.
     *
     * @param \Icicle\Loop\Events\Io $event
     */
    public function unreference(Io $event);

    /**
     * References a socket event if it was previously unreferenced. That is, if the event is pending the loop will
     * continue running.
     *
     * @param \Icicle\Loop\Events\Io $event
     */
    public function reference(Io $event);
}

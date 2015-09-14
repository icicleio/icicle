<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Events;

interface SocketEventInterface extends EventInterface
{
    /**
     * Returns the PHP resource.
     *
     * @return resource
     */
    public function getResource();
    
    /**
     * @param bool $expired
     */
    public function call(bool $expired);
    
    /**
     * @param bool $expired
     */
    public function __invoke(bool $expired);

    /**
     * @param callable $callback
     */
    public function setCallback(callable $callback);

    /**
     * Listens for data or the ability to write.
     *
     * @param int|float $timeout Number of seconds until the callback is invoked with $expired set to true if
     *     no data is received or the socket does not become writable. Use null for no timeout.
     */
    public function listen(float $timeout = 0);

    /**
     * Stops listening for data or the ability to write on the socket.
     */
    public function cancel();

    /**
     * Determines if the socket event is currently listening for data or the ability to write.
     *
     * @return bool
     */
    public function isPending(): bool;
    
    /**
     * Frees the resources used to listen for events on the socket.
     */
    public function free();
    
    /**
     * @return bool
     */
    public function isFreed(): bool;
}

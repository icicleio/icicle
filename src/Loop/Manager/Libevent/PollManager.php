<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager\Libevent;

use Icicle\Loop\Events\SocketEventInterface;

class PollManager extends SocketManager
{
    /**
     * {@inheritdoc}
     */
    protected function createEvent($base, SocketEventInterface $socket, callable $callback)
    {
        $event = event_new();
        event_set($event, $socket->getResource(), EV_READ, $callback, $socket);
        event_base_set($event, $base);
        
        return $event;
    }
}

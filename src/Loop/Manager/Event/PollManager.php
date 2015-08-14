<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager\Event;

use Event;
use EventBase;
use Icicle\Loop\Events\SocketEventInterface;

class PollManager extends SocketManager
{
    /**
     * {@inheritdoc}
     */
    protected function createEvent(EventBase $base, SocketEventInterface $socket, callable $callback): Event
    {
        return new Event($base, $socket->getResource(), Event::READ, $callback, $socket);
    }
}

<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager\Event;

use Event;
use EventBase;
use Icicle\Loop\EventLoop;
use Icicle\Loop\Manager\AbstractSignalManager;

class EventSignalManager extends AbstractSignalManager
{
    /**
     * @var \Event[]
     */
    private $events = [];

    /**
     * @param \Icicle\Loop\EventLoop $loop
     */
    public function __construct(EventLoop $loop)
    {
        parent::__construct($loop);

        $callback = $this->createSignalCallback();

        $base = $loop->getEventBase();

        foreach ($this->getSignalList() as $signal) {
            $event = new Event($base, $signal, Event::SIGNAL | Event::PERSIST, $callback);
            $event->add();
            $this->events[$signal] = $event;
        }
    }
    
    /**
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        foreach ($this->events as $event) {
            $event->free();
        }
    }
}
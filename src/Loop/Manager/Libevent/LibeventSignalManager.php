<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager\Libevent;

use Icicle\Loop\LibeventLoop;
use Icicle\Loop\Manager\AbstractSignalManager;

class LibeventSignalManager extends AbstractSignalManager
{
    /**
     * @var resource[]
     */
    private $events = [];

    /**
     * @param \Icicle\Loop\LibeventLoop $loop
     */
    public function __construct(LibeventLoop $loop)
    {
        parent::__construct($loop);

        $callback = $this->createSignalCallback();

        $base = $loop->getEventBase();

        foreach ($this->getSignalList() as $signo) {
            $event = event_new();
            event_set($event, $signo, EV_SIGNAL | EV_PERSIST, $callback);
            event_base_set($event, $base);
            event_add($event);
            $this->events[$signo] = $event;
        }
    }
    
    /**
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        foreach ($this->events as $event) {
            event_free($event);
        }
    }
}
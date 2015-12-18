<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Watcher;

abstract class Watcher
{
    /**
     * @var mixed
     */
    private $data;

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     *
     * @return mixed
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * An unreferenced event will allow the event loop to exit if no other watchers are pending.
     */
    abstract public function unreference();

    /**
     * Adds a reference to the event, causing the event loop to continue to run as long as the watcher is still pending.
     */
    abstract public function reference();
}

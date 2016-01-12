<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Observable\Internal;

use Icicle\Awaitable\Awaitable;
use Icicle\Awaitable\Delayed;

final class Placeholder
{
    /**
     * @var \Icicle\Awaitable\Delayed|null
     */
    private $delayed;

    /**
     * @var \Icicle\Awaitable\Awaitable
     */
    private $awaitable;

    /**
     * @var int
     */
    private $waiting = 0;

    /**
     * @param \Icicle\Awaitable\Awaitable $awaitable
     */
    public function __construct(Awaitable $awaitable)
    {
        $this->awaitable = $awaitable->uncancellable();
        $this->delayed = new Delayed();
    }

    /**
     * @return \Icicle\Awaitable\Awaitable
     */
    public function getAwaitable()
    {
        ++$this->waiting;
        return $this->awaitable;
    }

    /**
     * Notifies the placeholder that the consumer is ready.
     */
    public function ready()
    {
        if (0 === --$this->waiting) {
            $this->delayed->resolve();
        }
    }

    /**
     * Returns an awaitable that is fulfilled once all consumers are ready.
     *
     * @return \Icicle\Awaitable\Awaitable
     */
    public function wait()
    {
        return $this->delayed;
    }
}

<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager;

use Icicle\Loop\Events\ImmediateInterface;

interface ImmediateManagerInterface extends EventManagerInterface
{
    /**
     * Creates an immediate object connected to the manager.
     *
     * @param callable $callback
     * @param mixed[]|null $args
     *
     * @return \Icicle\Loop\Events\ImmediateInterface
     */
    public function create(callable $callback, array $args = []);

    /**
     * Puts the immediate in the loop again for execution.
     *
     * @param \Icicle\Loop\Events\ImmediateInterface $immediate
     */
    public function execute(ImmediateInterface $immediate);

    /**
     * Cancels the immeidate.
     *
     * @param \Icicle\Loop\Events\ImmediateInterface $immediate
     */
    public function cancel(ImmediateInterface $immediate);

    /**
     * Determines if the immediate is active in the loop.
     *
     * @param \Icicle\Loop\Events\ImmediateInterface $immediate
     *
     * @return bool
     */
    public function isPending(ImmediateInterface $immediate);

    /**
     * Calls the next pending immediate. Returns true if an immediate was executed, false if not.
     *
     * @return bool
     */
    public function tick();

    /**
     * Unreferences the given immediate, that is, if the immediate is pending in the loop, the loop should not continue
     * running.
     *
     * @param \Icicle\Loop\Events\ImmediateInterface $immediate
     */
    public function unreference(ImmediateInterface $immediate);

    /**
     * References an immediate if it was previously unreferenced. That is, if the immediate is pending the loop will
     * continue running.
     *
     * @param \Icicle\Loop\Events\ImmediateInterface $immediate
     */
    public function reference(ImmediateInterface $immediate);
}

<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Loop;

use Icicle\Loop\SelectLoop;

class SelectLoopTest extends AbstractLoopTest
{
    public function createLoop()
    {
        return new SelectLoop(true);
    }

    public function testListenAwaitWithExpiredTimeout()
    {
        list($readable, $writable) = $this->createSockets();
        
        $callback = $this->createCallback(1);
        
        $callback->method('__invoke')
                 ->with($this->identicalTo($writable), $this->identicalTo(true));
        
        $await = $this->loop->await($writable, $callback);

        fclose($writable); // A closed socket will never be writable, but is invalid in other loop implementations.

        $await->listen(self::TIMEOUT);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false);
    }

    /**
     * @requires extension pcntl
     */
    public function testSetSignalInterval()
    {
        $this->loop->signalInterval(self::TIMEOUT);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(SIGTERM));

        $signal = $this->loop->signal(SIGTERM, $callback);

        $this->loop->timer(1, false, function () {}); // Keep loop alive until signal arrives.

        $this->loop->queue('posix_kill', [posix_getpid(), SIGTERM]);

        $this->assertRunTimeLessThan([$this->loop, 'run'], self::TIMEOUT);
    }
}

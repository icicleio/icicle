<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Loop;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\SelectLoop;

class SelectLoopTest extends AbstractLoopTest
{
    public function createLoop(EventFactoryInterface $eventFactory)
    {
        return new SelectLoop(true, $eventFactory);
    }
    
    public function testEnabled()
    {
        $this->assertTrue(SelectLoop::enabled());
    }
    
    public function testListenAwaitWithExpiredTimeout()
    {
        list($readable, $writable) = $this->createSockets();
        
        fclose($writable); // A closed socket will never be writable, but is invalid in other loop implementations.
        
        $callback = $this->createCallback(1);
        
        $callback->method('__invoke')
                 ->with($this->identicalTo(true));
        
        $await = $this->loop->await($writable, $callback);
        
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

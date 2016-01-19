<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Observable;

use Icicle\Coroutine\Coroutine;
use Icicle\Loop;
use Icicle\Observable;
use Icicle\Tests\TestCase;

class ZipTestException extends \Exception {}

class ZipTest extends TestCase
{
    public function testBasicZip()
    {
        $count = 3;
        $observables = [];

        $observables[] = Observable\from([1, 2, 3]);
        $observables[] = Observable\from([4, 5, 6]);

        $observable = Observable\zip($observables);

        $i = 0;
        $callback = $this->createCallback($count);
        $callback->method('__invoke')
            ->will($this->returnCallback(function (array $values) use (&$i) {
                $this->assertSame([++$i, $i + 3], $values);
            }));

        $awaitable = new Coroutine($observable->each($callback));

        $this->assertSame($count, $awaitable->wait());
    }

    /**
     * @depends testBasicZip
     */
    public function testZipWithEarlyCompletingObservable()
    {
        $count = 4;
        $observables = [];

        $observables[] = Observable\from([1, 2, 3, 4]);
        $observables[] = Observable\from([4, 5, 6, 7, 8]);
        $observables[] = Observable\from([7, 8, 9, 10]);

        $observable = Observable\zip($observables);

        $i = 0;
        $callback = $this->createCallback($count);
        $callback->method('__invoke')
            ->will($this->returnCallback(function (array $values) use (&$i) {
                $this->assertSame([++$i, $i + 3, $i + 6], $values);
            }));

        $awaitable = new Coroutine($observable->each($callback));

        $this->assertSame($count, $awaitable->wait());
    }

    public function testZipWithFailingObservable()
    {
        $reason = new ZipTestException();
        $observables = [];

        $observables[] = Observable\of(1, 2, 3);
        $observables[] = Observable\fail($reason);

        $observable = Observable\zip($observables);

        $awaitable = new Coroutine($observable->each($this->createCallback(0)));

        try {
            $awaitable->wait();
            $this->fail('Failing observable should fail observable returned from zip().');
        } catch (ZipTestException $exception) {
            $this->assertSame($reason, $exception);
        }
    }
}

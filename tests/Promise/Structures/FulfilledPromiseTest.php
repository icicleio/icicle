<?php
namespace Icicle\Tests\Promise\Structures;

use Icicle\Promise\Promise;
use Icicle\Promise\Structures\FulfilledPromise;
use Icicle\Tests\TestCase;

/**
 * Tests the constructor only. All other methods are covered by PromiseTest.
 *
 * @requires PHP 5.4
 */
class FulfilledPromiseTest extends TestCase
{
    /**
     * @expectedException Icicle\Promise\Exception\TypeException
     */
    public function testCannotUsePromseAsValue()
    {
        $promise = new Promise(function () {});
        
        $fulfilled = new FulfilledPromise($promise);
    }
}

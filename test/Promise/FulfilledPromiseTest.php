<?php
namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\FulfilledPromise;
use Icicle\Promise\Promise;
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

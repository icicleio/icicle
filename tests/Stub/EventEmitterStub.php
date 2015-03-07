<?php
namespace Icicle\Tests\Stub;

use Icicle\EventEmitter\EventEmitterInterface;
use Icicle\EventEmitter\EventEmitterTrait;

class EventEmitterStub implements EventEmitterInterface
{
    use EventEmitterTrait {
        createEvent as public;
    }
}

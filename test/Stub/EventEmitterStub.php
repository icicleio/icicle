<?php
namespace Icicle\Tests\Stub;

use Icicle\Event\EventEmitterInterface;
use Icicle\Event\EventEmitterTrait;

class EventEmitterStub implements EventEmitterInterface
{
    use EventEmitterTrait {
        createEvent as public;
        emit as public;
    }
}

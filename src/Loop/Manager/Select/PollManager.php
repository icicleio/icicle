<?php
namespace Icicle\Loop\Manager\Select;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Manager\PollManagerInterface;
use Icicle\Loop\SelectLoop;

class PollManager extends SocketManager implements PollManagerInterface
{
    /**
     * @var EventFactoryInterface
     */
    private $factory;
    
    /**
     * @param   SelectLoop $loop
     * @param   EventFactoryInterface $factory
     */
    public function __construct(SelectLoop $loop, EventFactoryInterface $factory)
    {
        parent::__construct($loop);
        
        $this->factory = $factory;
    }
    
    /**
     * @inheritdoc
     */
    protected function createSocketEvent($resource, callable $callback)
    {
        return $this->factory->createPoll($this, $resource, $callback);
    }
}

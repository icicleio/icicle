<?php
namespace Icicle\Loop\Manager\Select;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Manager\AwaitManagerInterface;
use Icicle\Loop\SelectLoop;

class AwaitManager extends SocketManager implements AwaitManagerInterface
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
        return $this->factory->createAwait($this, $resource, $callback);
    }
}

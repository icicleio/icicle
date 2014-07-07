<?php
namespace Icicle\Loop;

class LoopFactory
{
    /**
     * @return  LoopInterface
     */
    public static function create()
    {
        if (EventLoop::enabled()) {
            return new EventLoop();
        }
        
        if (LibeventLoop::enabled()) {
            return new LibeventLoop();
        }
        
        // LibevLoop is not used stince it is still experimental.
        
        return new SelectLoop();
    }
}

<?php
namespace Icicle\Loop\Events;

interface EventInterface
{
    /**
     * @return  bool
     */
    public function isPending();
    
    /**
     * @return  callable
     */
    public function getCallback();
}

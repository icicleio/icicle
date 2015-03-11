<?php
namespace Icicle\Loop\Events;

interface EventInterface
{
    /**
     * @return  bool
     *
     * @api
     */
    public function isPending();
    
    /**
     * @return  callable
     */
    public function getCallback();
}

<?php
namespace Icicle\Coroutine;

use Icicle\Promise\PromiseInterface;

interface CoroutineInterface extends PromiseInterface
{
    /**
     * Pauses the coroutine.
     */
    public function pause();
    
    /**
     * Resumes the coroutine if it was paused.
     */
    public function resume();
    
    /**
     * @return bool
     */
    public function isPaused();
}

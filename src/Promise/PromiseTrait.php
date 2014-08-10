<?php
namespace Icicle\Promise;

use Exception;

trait PromiseTrait
{
    /**
     * {@inheritdoc}
     */
    public function always(callable $onResolved)
    {
        return $this->then($onResolved, $onResolved);
    }
    
    /**
     * {@inheritdoc}
     */
    public function capture(callable $onRejected, callable $typeFilter = null)
    {
        if (null === $typeFilter) {
            return $this->then(null, $onRejected);
        }
        
        return $this->then(null, function (Exception $exception) use ($onRejected, $typeFilter) {
            if ($typeFilter($exception)) {
                return $onRejected($exception);
            }
            
            return $this; // $this is now a rejected promise.
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function after(callable $onResolved)
    {
        $this->done($onResolved, $onResolved);
    }
    
    /**
     * {@inheritdoc}
     */
    public function otherwise(callable $onRejected)
    {
        $this->done(null, $onRejected);
    }
    
    /**
     * {@inheritdoc}
     */
    public function tap(callable $onFulfilled) {
        return $this->then(function ($value) use ($onFulfilled) {
            $onFulfilled($value);
            return $this; // $this is now a fulfilled promise.
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function cleanup(callable $onResolved)
    {
        return $this->always(function () use ($onResolved) {
            $onResolved();
            return $this; // $this is now a resolved promise.
        });
    }
}

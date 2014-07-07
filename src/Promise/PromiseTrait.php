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
            
            throw $exception;
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
        return $this->done(null, $onRejected);
    }
    
    /**
     * {@inheritdoc}
     */
    public function tap(callable $onFulfilled) {
        return $this->then(function ($value) use ($onFulfilled) {
            $onFulfilled($value);
            return $value;
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function cleanup(callable $onResolved)
    {
        return $this->then(
            function ($value) use ($onResolved) {
                $onResolved();
                return $value;
            },
            function (Exception $exception) use ($onResolved) {
                $onResolved();
                throw $exception;
            }
        );
    }
}

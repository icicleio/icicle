<?php
namespace Icicle\Promise;

use Closure;
use Exception;
use ReflectionFunction;
use ReflectionMethod;

trait PromiseTrait
{
    /**
     * @param   callable $onFulfilled
     * @param   callable $onRejected
     *
     * @return  PromiseInterface
     */
    abstract public function then(callable $onFulfilled = null, callable $onRejected = null);
    
    /**
     * @param   callable $onFulfilled
     * @param   callable $onRejected
     *
     * @return  PromiseInterface
     */
    abstract public function done(callable $onFulfilled = null, callable $onRejected = null);
    
    /**
     * @inheritdoc
     */
    public function always(callable $onResolved)
    {
        return $this->then($onResolved, $onResolved);
    }
    
    /**
     * @inheritdoc
     */
    public function capture(callable $onRejected)
    {
        return $this->then(null, function (Exception $exception) use ($onRejected) {
            if (is_array($onRejected)) { // Methods passed as an array.
                $reflection = new ReflectionMethod($onRejected[0], $onRejected[1]);
            } elseif (is_object($onRejected) && !$onRejected instanceof Closure) { // Callable objects that are not Closures.
                $reflection = new ReflectionMethod($onRejected, '__invoke');
            } else { // Everything else (note method names delimited by :: do not work with $callable() syntax).
                $reflection = new ReflectionFunction($onRejected);
            }
            
            $parameters = $reflection->getParameters();
            
            if (empty($parameters)) { // No parameters defined.
                return $onRejected($exception); // Providing argument in case func_get_args() is used in function.
            }
            
            $class = $parameters[0]->getClass();
            
            if (null === $class || $class->isInstance($exception)) { // No typehint or matching typehint.
                return $onRejected($exception);
            }
            
            return $this; // Typehint does not match. $this is now a rejected promise.
        });
    }
    
    /**
     * @inheritdoc
     */
    public function after(callable $onResolved)
    {
        $this->done($onResolved, $onResolved);
    }
    
    /**
     * @inheritdoc
     */
    public function tap(callable $onFulfilled) {
        return $this->then(function ($value) use ($onFulfilled) {
            $onFulfilled($value);
            return $this; // $this is now a fulfilled promise.
        });
    }
    
    /**
     * @inheritdoc
     */
    public function cleanup(callable $onResolved)
    {
        return $this->always(function () use ($onResolved) {
            $onResolved();
            return $this; // $this is now a resolved promise.
        });
    }
}

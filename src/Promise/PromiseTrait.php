<?php
namespace Icicle\Promise;

use Icicle\Promise\Exception\UnexpectedTypeError;

trait PromiseTrait
{
    /**
     * @param callable $onFulfilled
     * @param callable $onRejected
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    abstract public function then(callable $onFulfilled = null, callable $onRejected = null);
    
    /**
     * {@inheritdoc}
     */
    public function capture(callable $onRejected)
    {
        return $this->then(null, function (\Exception $exception) use ($onRejected) {
            if (is_array($onRejected)) { // Methods passed as an array.
                $reflection = new \ReflectionMethod($onRejected[0], $onRejected[1]);
            } elseif (is_object($onRejected) && !$onRejected instanceof \Closure) { // Callable objects.
                $reflection = new \ReflectionMethod($onRejected, '__invoke');
            } else { // Everything else (note method names delimited by :: do not work with $callable() syntax).
                $reflection = new \ReflectionFunction($onRejected);
            }
            
            $parameters = $reflection->getParameters();
            
            if (empty($parameters)) { // No parameters defined.
                return $onRejected($exception); // Providing argument in case func_get_args() is used in function.
            }
            
            $class = $parameters[0]->getClass();
            
            if (null === $class || $class->isInstance($exception)) { // No type-hint or matching type-hint.
                return $onRejected($exception);
            }
            
            return $this; // Type-hint does not match.
        });
    }

    /**
     * {@inheritdoc}
     */
    public function tap(callable $onFulfilled)
    {
        return $this->then(function ($value) use ($onFulfilled) {
            return resolve($onFulfilled($value))->then(function () {
                return $this;
            });
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function cleanup(callable $onResolved)
    {
        $onResolved = function () use ($onResolved) {
            return resolve($onResolved())->then(function () {
                return $this;
            });
        };

        return $this->then($onResolved, $onResolved);
    }

    /**
     * {@inheritdoc}
     */
    public function splat(callable $onFulfilled)
    {
        return $this->then(function ($values) use ($onFulfilled) {
            if ($values instanceof \Traversable) {
                $values = iterator_to_array($values);
            } elseif (!is_array($values)) {
                throw new UnexpectedTypeError(sprintf(
                    'Expected array or Traversable for promise result, got %s',
                    is_object($values) ? get_class($values) : gettype($values)
                ));
            }

            ksort($values); // Ensures correct argument order.
            return call_user_func_array($onFulfilled, $values);
        });
    }
}

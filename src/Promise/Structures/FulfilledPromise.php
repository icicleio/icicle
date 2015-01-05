<?php
namespace Icicle\Promise\Structures;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Exception\TypeException;
use Icicle\Promise\Promise;
use Icicle\Promise\PromiseInterface;

class FulFilledPromise extends ResolvedPromise
{
    /**
     * @var     mixed
     */
    private $value;
    
    /**
     * @param   mixed $value Anything other than a PromiseInterface or PromisorInterface object.
     *
     * @throws  TypeException Thrown if a PromiseInterface or PromisorInterface is given as the value.
     */
    public function __construct($value)
    {
        if ($value instanceof PromiseInterface) {
            throw new TypeException('Cannot use a PromiseInterface as a fulfilled promise value.');
        }
        
        $this->value = $value;
    }
    
    /**
     * {@inheritdoc}
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        if (null === $onFulfilled) {
            return new static($this->value);
        }
        
        return new Promise(function ($resolve, $reject) use ($onFulfilled) {
            Loop::schedule(function () use ($resolve, $reject, $onFulfilled) {
                try {
                    $resolve($onFulfilled($this->value));
                } catch (Exception $exception) {
                    $reject($exception);
                }
            });
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null)
    {
        if (null !== $onFulfilled) {
            Loop::schedule($onFulfilled, $this->value);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function delay($time)
    {
        return new Promise(
            function ($resolve) use (&$timer, $time) {
                $timer = Loop::timer($time, function () use ($resolve) {
                    $resolve($this);
                });
            },
            function () use (&$timer) {
                $timer->cancel();
            }
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function isFulfilled()
    {
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isRejected()
    {
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getResult()
    {
        return $this->value;
    }
}

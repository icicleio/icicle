<?php
namespace Icicle\Promise\Structures;

use Icicle\Loop;
use Icicle\Promise\Exception\InvalidArgumentError;
use Icicle\Promise\Promise;
use Icicle\Promise\PromiseInterface;

class FulfilledPromise extends ResolvedPromise
{
    /**
     * @var mixed
     */
    private $value;
    
    /**
     * @param mixed $value Anything other than a PromiseInterface object.
     *
     * @throws \Icicle\Promise\Exception\InvalidArgumentError If a PromiseInterface is given as the value.
     */
    public function __construct($value)
    {
        if ($value instanceof PromiseInterface) {
            throw new InvalidArgumentError('Cannot use a PromiseInterface as a fulfilled promise value.');
        }
        
        $this->value = $value;
    }
    
    /**
     * {@inheritdoc}
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null): PromiseInterface
    {
        if (null === $onFulfilled) {
            return $this;
        }
        
        return new Promise(function ($resolve, $reject) use ($onFulfilled) {
            Loop\queue(function () use ($resolve, $reject, $onFulfilled) {
                try {
                    $resolve($onFulfilled($this->value));
                } catch (\Throwable $exception) {
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
            Loop\queue($onFulfilled, $this->value);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function delay(float $time): PromiseInterface
    {
        return new Promise(
            function ($resolve) use (&$timer, $time) {
                $timer = Loop\timer($time, function () use ($resolve) {
                    $resolve($this);
                });
            },
            function () use (&$timer) {
                $timer->stop();
            }
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function isFulfilled(): bool
    {
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isRejected(): bool
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

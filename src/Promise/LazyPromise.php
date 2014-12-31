<?php
namespace Icicle\Promise;

use Exception;

class LazyPromise implements PromiseInterface
{
    use PromiseTrait;
    
    /**
     * @var callable|PromiseInterface
     */
    private $promise;
    
    /**
     * @param   callable $promisor
     */
    public function __construct(callable $promisor)
    {
        $this->promise = $promisor;
    }
    
    /**
     * @return  PromiseInterface
     */
    protected function getPromise()
    {
        if (!$this->promise instanceof PromiseInterface) {
            $promisor = $this->promise;
            
            try {
                $this->promise = Promise::resolve($promisor());
            } catch (Exception $exception) {
                $this->promise = Promise::reject($exception);
            }
        }
        
        return $this->promise;
    }
    
    /**
     * {@inheritdoc}
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        return $this->getPromise()->then($onFulfilled, $onRejected);
    }
    
    /**
     * {@inheritdoc}
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null)
    {
        $this->getPromise()->done($onFulfilled, $onRejected);
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancel(Exception $exception = null)
    {
        $this->getPromise()->cancel($exception);
    }
    
    /**
     * {@inheritdoc}
     */
    public function timeout($timeout, Exception $exception = null)
    {
        return $this->getPromise()->timeout($timeout, $exception);
    }
    
    /**
     * {@inheritdoc}
     */
    public function delay($time)
    {
        return $this->getPromise()->delay($time);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending()
    {
        return $this->getPromise()->isPending();
    }
    
    /**
     * {@inheritdoc}
     */
    public function isFulfilled()
    {
        return $this->getPromise()->isFulfilled();
    }
    
    /**
     * {@inheritdoc}
     */
    public function isRejected()
    {
        return $this->getPromise()->isRejected();
    }
    
    /**
     * {@inheritdoc}
     */
    public function getResult()
    {
        return $this->getPromise()->getResult();
    }
    
    /**
     * @param   callable $promisor
     * @param   mixed ...$args
     *
     * @return  LazyPromise
     */
    public static function call(callable $promisor /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 1);
        
        return static::create($promisor, $args);
    }
    
    /**
     * @param   callable $promisor
     * @param   mixed[] $args
     *
     * @return  LazyPromise
     */
    public static function create(callable $promisor, array $args = [])
    {
        return new static(function () use ($promisor, $args) {
            return call_user_func_array($promisor, $args);
        });
    }
}

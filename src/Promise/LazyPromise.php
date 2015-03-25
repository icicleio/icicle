<?php
namespace Icicle\Promise;

class LazyPromise implements PromiseInterface
{
    use PromiseTrait;
    
    /**
     * @var \Icicle\Promise\PromiseInterface|null
     */
    private $promise;
    
    /**
     * @var callable|null
     */
    private $promisor;
    
    /**
     * @param   callable $promisor
     */
    public function __construct(callable $promisor)
    {
        $this->promisor = $promisor;
    }
    
    /**
     * @return  PromiseInterface
     */
    protected function getPromise()
    {
        if (null === $this->promise) {
            $promisor = $this->promisor;
            $this->promisor = null;
            
            try {
                $this->promise = Promise::resolve($promisor());
            } catch (\Exception $exception) {
                $this->promise = Promise::reject($exception);
            }
        }
        
        return $this->promise;
    }
    
    /**
     * @inheritdoc
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        return $this->getPromise()->then($onFulfilled, $onRejected);
    }
    
    /**
     * @inheritdoc
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null)
    {
        $this->getPromise()->done($onFulfilled, $onRejected);
    }
    
    /**
     * @inheritdoc
     */
    public function cancel($reason = null)
    {
        $this->getPromise()->cancel($reason);
    }
    
    /**
     * @inheritdoc
     */
    public function timeout($timeout, $reason = null)
    {
        return $this->getPromise()->timeout($timeout, $reason);
    }
    
    /**
     * @inheritdoc
     */
    public function delay($time)
    {
        return $this->getPromise()->delay($time);
    }
    
    /**
     * @inheritdoc
     */
    public function isPending()
    {
        return $this->getPromise()->isPending();
    }
    
    /**
     * @inheritdoc
     */
    public function isFulfilled()
    {
        return $this->getPromise()->isFulfilled();
    }
    
    /**
     * @inheritdoc
     */
    public function isRejected()
    {
        return $this->getPromise()->isRejected();
    }
    
    /**
     * @inheritdoc
     */
    public function getResult()
    {
        return $this->getPromise()->getResult();
    }
    
    /**
     * @inheritdoc
     */
    public function unwrap()
    {
        return $this->getPromise()->unwrap();
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
    public static function create(callable $promisor, array $args = null)
    {
        return new static(function () use ($promisor, $args) {
            if (empty($args)) {
                return $promisor();
            }
            
            return call_user_func_array($promisor, $args);
        });
    }
}

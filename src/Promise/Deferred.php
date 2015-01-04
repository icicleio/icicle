<?php
namespace Icicle\Promise;

use Exception;

class Deferred implements PromisorInterface
{
    /**
     * @var     Promise
     */
    private $promise;
    
    /**
     * @var     callable
     */
    private $resolve;
    
    /**
     * @var     callable
     */
    private $reject;
    
    /**
     * @param   callable|null $onCancelled
     */
    public function __construct(callable $onCancelled = null)
    {
        $this->promise = new Promise(function ($resolve, $reject) {
            $this->resolve = $resolve;
            $this->reject = $reject;
        }, $onCancelled);
    }
    
    /**
     * @return  PromiseInterface
     */
    public function getPromise()
    {
        return $this->promise;
    }
    
    /**
     * Fulfill the promise with the given value.
     *
     * @param   mixed $value
     */
    public function resolve($value = null)
    {
        $resolve = $this->resolve;
        $resolve($value);
    }
    
    /**
     * Reject the promise the the given Exception.
     *
     * @param   Exception $exception
     */
    public function reject(Exception $exception)
    {
        $reject = $this->reject;
        $reject($exception);
    }
}

<?php
namespace Icicle\Coroutine;

use Icicle\Coroutine\Exception\InvalidCallableError;
use Icicle\Promise;

if (!function_exists(__NAMESPACE__ . '\wrap')) {
    /**
     * Wraps the function returning a \Generator in a callable function that returns a new coroutine each time the
     * function is called.
     *
     * @param callable $worker
     *
     * @return callable
     */
    function wrap(callable $worker)
    {
        /**
         * @param mixed ...$args
         *
         * @return \Icicle\Coroutine\Coroutine
         *
         * @throws \Icicle\Coroutine\Exception\InvalidCallableException If the callable throws an exception or does
         *     not return a Generator.
         */
        return function (/* ...$args */) use ($worker) {
            $args = func_get_args();
            array_unshift($args, $worker);
            return call_user_func_array(__NAMESPACE__ . '\create', $args);
        };
    }

    /**
     * Calls the callable with the given arguments which must return a \Generator, which is then made into a coroutine
     * and returned.
     *
     * @param callable $worker
     * @param mixed ...$args
     *
     * @return \Icicle\Coroutine\Coroutine
     *
     * @throws \Icicle\Coroutine\Exception\InvalidCallableError If the callable throws an exception or does not
     *     return a Generator.
     */
    function create(callable $worker /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 1);

        try {
            if (empty($args)) {
                $generator = $worker();
            } else {
                $generator = call_user_func_array($worker, $args);
            }
        } catch (\Exception $exception) {
            throw new InvalidCallableError('The callable threw an exception.', $worker, $exception);
        }

        if (!$generator instanceof \Generator) {
            throw new InvalidCallableError('The callable did not return a Generator.', $worker);
        }

        return new Coroutine($generator);
    }

    /**
     * @coroutine
     *
     * @param float $time Time to sleep in seconds.
     *
     * @return \Generator
     *
     * @resolve float Actual time slept in seconds.
     */
    function sleep($time)
    {
        $start = (yield Promise\resolve(microtime(true))->delay($time));

        yield microtime(true) - $start;
    }
}

<?php
namespace Icicle\Promise;

interface PromisorInterface
{
    /**
     * Returns the internal PromiseInterface object.
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    public function getPromise(): PromiseInterface;
}

<?php
namespace Icicle\Promise;

interface PromisorInterface
{
    /**
     * Returns the internal PromiseInterface object.
     *
     * @return  PromiseInterface
     */
    public function getPromise();
}

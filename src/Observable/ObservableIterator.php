<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Observable;

interface ObservableIterator
{
    /**
     * @coroutine
     *
     * Resolves with true if a new value is available by calling getCurrent() or false if the observable has completed.
     * Calling getCurrent() will throw an exception if the observable completed. If an error occurs with the observable,
     * this coroutine will be rejected with the exception used to fail the observable.
     *
     * @return \Generator
     *
     * @resolve bool
     *
     * @throws \Exception Exception used to fail the observable.
     */
    public function isValid();

    /**
     * Gets the last emitted value or throws an exception if the observable has completed.
     *
     * @return mixed Value emitted from observable.
     *
     * @throws \Icicle\Observable\Exception\CompletedError If the observable has successfully completed.
     * @throws \Icicle\Observable\Exception\UninitializedError If isValid() was not called before calling this method.
     * @throws \Exception Exception used to fail the observable.
     */
    public function getCurrent();

    /**
     * Gets the return value of the observable or throws the failure reason. Also throws an exception if the
     * observable has not completed.
     *
     * @return mixed Final return value of the observable.
     *
     * @throws \Icicle\Observable\Exception\IncompleteError If the observable has not completed.
     * @throws \Exception Exception used to fail the observable.
     */
    public function getReturn();
}

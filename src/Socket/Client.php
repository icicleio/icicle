<?php
namespace Icicle\Socket;

use Exception;
use Icicle\Promise\Promise;
use Icicle\Socket\Exception\FailureException;
use Icicle\Socket\Exception\InvalidArgumentException;

abstract class Client extends Stream
{
    /**
     * Remote IP address (as an int).
     *
     * @return  int
     */
    abstract public function getRemoteAddress();
    
    /**
     * Remote port number.
     *
     * @return  int
     */
    abstract public function getRemotePort();
    
    /**
     * Local IP address (as an int).
     *
     * @return  int
     */
    abstract public function getLocalAddress();
    
    /**
     * Local port number.
     *
     * @return  int
     */
    abstract public function getLocalPort();
}

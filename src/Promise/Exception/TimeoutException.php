<?php
namespace Icicle\Promise\Exception;

class TimeoutException extends ReasonException
{
    public function __construct($reason)
    {
        parent::__construct($reason, 'Promise timed out.');
    }
}

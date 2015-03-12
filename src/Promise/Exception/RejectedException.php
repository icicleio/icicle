<?php
namespace Icicle\Promise\Exception;

class RejectedException extends ReasonException
{
    public function __construct($reason)
    {
        parent::__construct($reason, 'Promise rejected.');
    }
}

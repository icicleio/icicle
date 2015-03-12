<?php
namespace Icicle\Promise\Exception;

class CancelledException extends ReasonException
{
    public function __construct($reason)
    {
        parent::__construct($reason, 'Promise cancelled.');
    }
}

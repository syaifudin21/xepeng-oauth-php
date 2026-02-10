<?php

namespace Xepeng\OAuth\Exceptions;

use Exception;

class OAuthException extends Exception
{
    private $error;

    public function __construct($message, $error = null, $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->error = $error;
    }

    public function getError()
    {
        return $this->error;
    }
}

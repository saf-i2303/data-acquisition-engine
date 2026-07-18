<?php

namespace App\Exceptions;

class ResourceNotFoundException extends ApiException
{
    protected int $statusCode = 404;
    protected string $errorCode = 'NOT_FOUND';

    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
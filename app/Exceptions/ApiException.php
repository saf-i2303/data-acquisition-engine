<?php

namespace App\Exceptions;

use Exception;

abstract class ApiException extends Exception
{
    protected int $statusCode = 500;
    protected string $errorCode = 'INTERNAL_ERROR';

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
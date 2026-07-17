<?php

namespace App\Exceptions;

class ExternalServiceException extends ApiException
{
    protected int $statusCode = 502;
    protected string $errorCode = 'EXTERNAL_SERVICE_ERROR';

    public function __construct(string $service, string $reason = '')
    {
        $message = "Gagal mengambil data dari layanan eksternal [{$service}]";
        $message .= $reason !== '' ? ": {$reason}" : '.';

        parent::__construct($message);
    }
}
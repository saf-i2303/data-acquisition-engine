<?php

namespace App\Services\Contracts;

interface LocationFinderServiceInterface
{
    /**
     * Cari lokasi/tempat berdasarkan query teks bebas.
     *
     * @throws \App\Exceptions\ExternalServiceException
     * @throws \App\Exceptions\ResourceNotFoundException
     */
    public function search(string $query): array;
}
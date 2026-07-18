<?php

namespace App\Services\Contracts;

interface DomainIntelligenceServiceInterface
{
    /**
     * Cari data registrasi domain lewat RDAP
     *
     * @throws \App\Exceptions\ExternalServiceException
     * @throws \App\Exceptions\ResourceNotFoundException
     */
    public function lookup(string $domain): array;
}
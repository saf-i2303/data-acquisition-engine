<?php

namespace App\Services\Contracts;

interface WebsiteMetadataServiceInterface
{
    /**
     * Ambil dan parsing metadata dari sebuah URL website.
     *
     * @throws \App\Exceptions\ExternalServiceException
     */
    public function extract(string $url): array;
}
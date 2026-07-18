<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Services\Contracts\DomainIntelligenceServiceInterface;
use App\Services\Contracts\LocationFinderServiceInterface;
use App\Services\Contracts\WebsiteMetadataServiceInterface;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

class CompanyInformationService
{
    public function __construct(
        private readonly WebsiteMetadataServiceInterface $websiteService,
        private readonly DomainIntelligenceServiceInterface $domainService,
        private readonly LocationFinderServiceInterface $locationService,
    ) {
    }

    public function gather(string $domain, ?string $companyName = null): array
    {
        $websiteUrl = str_starts_with($domain, 'http') ? $domain : "https://{$domain}";
        $locationQuery = $companyName ?? $domain;

        $website = $this->safeCall(fn () => $this->websiteService->extract($websiteUrl), 'website');
        $domainInfo = $this->safeCall(fn () => $this->domainService->lookup($this->normalizeDomain($domain)), 'domain');
        $location = $this->safeCall(fn () => $this->locationService->search($locationQuery), 'location');

        return [
            'query' => [
                'domain' => $domain,
                'company_name' => $companyName,
            ],
            'website' => $website['data'],
            'website_error' => $website['error'],
            'domain' => $domainInfo['data'],
            'domain_error' => $domainInfo['error'],
            'location' => $location['data'],
            'location_error' => $location['error'],
        ];
    }

    private function safeCall(Closure $callback, string $connector): array
    {
        try {
            return ['data' => $callback(), 'error' => null];
        } catch (ApiException $e) {
            Log::warning("Connector [{$connector}] gagal", [
                'code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ]);

            return [
                'data' => null,
                'error' => ['code' => $e->getErrorCode(), 'message' => $e->getMessage()],
            ];
        } catch (Throwable $e) {
            Log::error("Connector [{$connector}] gagal tak terduga", ['message' => $e->getMessage()]);

            return [
                'data' => null,
                'error' => ['code' => 'UNKNOWN_ERROR', 'message' => 'Terjadi kesalahan tak terduga.'],
            ];
        }
    }

    private function normalizeDomain(string $domain): string
    {
        if (str_starts_with($domain, 'http')) {
            return parse_url($domain, PHP_URL_HOST) ?? $domain;
        }

        return $domain;
    }
}
<?php

namespace App\Services;

use App\Exceptions\ExternalServiceException;
use App\Exceptions\ResourceNotFoundException;
use App\Services\Contracts\DomainIntelligenceServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DomainIntelligenceService implements DomainIntelligenceServiceInterface
{
    private const TIMEOUT_SECONDS = 20;
    private const BASE_URL = 'https://rdap.org/domain/';
    private const CACHE_TTL_SECONDS = 3600;

    public function lookup(string $domain): array
    {
        $cacheKey = 'domain_rdap:' . strtolower($domain);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($domain) {
            Log::info('Memulai pencarian data RDAP domain', ['domain' => $domain]);

            $data = $this->fetchRdap($domain);

            $result = [
                'domain' => strtolower($data['ldhName'] ?? $domain),
                'handle' => $data['handle'] ?? null,
                'registrar' => $this->extractEntityField($data, 'registrar', 'fn'),
                'abuse_contact' => $this->extractEntityField($data, 'abuse', 'email'),
                'registered_at' => $this->extractEventDate($data, 'registration'),
                'expired_at' => $this->extractEventDate($data, 'expiration'),
                'last_updated' => $this->extractEventDate($data, 'last changed'),
                'status' => $data['status'] ?? [],
                'nameservers' => collect($data['nameservers'] ?? [])
                    ->pluck('ldhName')
                    ->filter()
                    ->values()
                    ->all(),
            ];

            Log::info('Pencarian data RDAP selesai', ['domain' => $domain]);

            return $result;
        });
    }

    private function fetchRdap(string $domain): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->acceptJson()
                ->get(self::BASE_URL . $domain);
        } catch (Throwable $e) {
            Log::error('Gagal fetch RDAP', ['domain' => $domain, 'message' => $e->getMessage()]);
            throw new ExternalServiceException('RDAP', $e->getMessage());
        }

        if ($response->status() === 404) {
            Log::warning('Domain tidak ditemukan di RDAP', ['domain' => $domain]);
            throw new ResourceNotFoundException(
                "Domain '{$domain}' tidak ditemukan pada registry RDAP."
            );
        }

        if ($response->failed()) {
            throw new ExternalServiceException('RDAP', "status HTTP {$response->status()}");
        }

        return $response->json() ?? [];
    }

    /**
     * Cari nilai field vCard tertentu dari entity RDAP dengan role tertentu.
     * Dipakai untuk registrar (role: registrar, field: fn/nama) dan
     * abuse contact (role: abuse, field: email) — dua kasus ini punya
     * struktur pencarian yang identik, hanya beda role & field target.
     */
    private function extractEntityField(array $data, string $role, string $vcardField): ?string
    {
        foreach ($data['entities'] ?? [] as $entity) {
            if (!in_array($role, $entity['roles'] ?? [], true)) {
                continue;
            }

            $vcard = $entity['vcardArray'][1] ?? [];

            foreach ($vcard as $field) {
                if (($field[0] ?? null) === $vcardField) {
                    return $field[3] ?? null;
                }
            }
        }

        return null;
    }

    private function extractEventDate(array $data, string $action): ?string
    {
        foreach ($data['events'] ?? [] as $event) {
            if (($event['eventAction'] ?? null) === $action) {
                return $event['eventDate'] ?? null;
            }
        }

        return null;
    }
}
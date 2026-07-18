<?php

namespace App\Services;

use App\Exceptions\ExternalServiceException;
use App\Exceptions\ResourceNotFoundException;
use App\Services\Contracts\LocationFinderServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class LocationFinderService implements LocationFinderServiceInterface
{
    private const TIMEOUT_SECONDS = 10;
    private const BASE_URL = 'https://nominatim.openstreetmap.org/search';

    public function search(string $query): array
    {
        Log::info('Memulai pencarian lokasi', ['query' => $query]);

        $result = $this->fetchFirstResult($query);

        $data = [
            'display_name' => $result['display_name'] ?? null,
            'latitude' => $result['lat'] ?? null,
            'longitude' => $result['lon'] ?? null,
            'importance' => $result['importance'] ?? null,
            'osm_type' => $result['osm_type'] ?? null,
            'address' => $result['address'] ?? [],
        ];

        Log::info('Pencarian lokasi selesai', ['query' => $query]);

        return $data;
    }

    private function fetchFirstResult(string $query): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    // Nominatim usage policy mewajibkan User-Agent deskriptif.
                    'User-Agent' => 'DataAcquisitionEngine/1.0',
                ])
                ->get(self::BASE_URL, [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'limit' => 1,
                ]);
        } catch (Throwable $e) {
            Log::error('Gagal fetch Nominatim', ['query' => $query, 'message' => $e->getMessage()]);
            throw new ExternalServiceException('Nominatim', $e->getMessage());
        }

        if ($response->failed()) {
            throw new ExternalServiceException('Nominatim', "status HTTP {$response->status()}");
        }

        $results = $response->json() ?? [];

        if (empty($results)) {
            Log::warning('Tidak ada lokasi ditemukan', ['query' => $query]);
            throw new ResourceNotFoundException(
                "Tidak ditemukan lokasi yang cocok untuk '{$query}'."
            );
        }

        return $results[0];
    }
}
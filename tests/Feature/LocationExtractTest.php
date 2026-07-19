<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocationExtractTest extends TestCase
{
    public function test_returns_location_data_for_valid_query(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'display_name' => 'PT Telkom Indonesia, Jakarta, Indonesia',
                    'lat' => '-6.1751',
                    'lon' => '106.8650',
                    'importance' => 0.75,
                    'osm_type' => 'way',
                    'address' => [
                        'city' => 'Jakarta',
                        'country' => 'Indonesia',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/extract/location', [
            'query' => 'PT Telkom Indonesia',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'display_name' => 'PT Telkom Indonesia, Jakarta, Indonesia',
                    'latitude' => '-6.1751',
                    'longitude' => '106.8650',
                    'osm_type' => 'way',
                    'match_quality' => 'reliable',
                ],
            ]);
    }

    public function test_returns_uncertain_match_quality_for_low_importance(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'display_name' => 'Hasil Kurang Relevan',
                    'lat' => '-6.1751',
                    'lon' => '106.8650',
                    'importance' => 0.05,
                    'osm_type' => 'way',
                    'address' => [],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/extract/location', [
            'query' => 'paper.id',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.match_quality', 'uncertain');
    }

    public function test_returns_validation_error_when_query_missing(): void
    {
        $response = $this->postJson('/api/extract/location', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR'],
            ]);
    }

    public function test_returns_not_found_when_no_location_matches(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);

        $response = $this->postJson('/api/extract/location', [
            'query' => 'lokasi-yang-tidak-ada-xyz',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND'],
            ]);
    }
}
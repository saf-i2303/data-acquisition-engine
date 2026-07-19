<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompanyInformationTest extends TestCase
{
    public function test_returns_combined_data_when_all_connectors_succeed(): void
    {
        Http::fake([
            'https://paper.id' => Http::response('<html><head><title>Paper.id</title></head></html>', 200),
            'rdap.org/*' => Http::response([
                'ldhName' => 'PAPER.ID',
                'status' => ['active'],
                'entities' => [],
                'events' => [],
                'nameservers' => [],
            ], 200),
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'display_name' => 'Paper.id, Jakarta, Indonesia',
                    'lat' => '-6.1751',
                    'lon' => '106.8650',
                    'importance' => 0.5,
                    'osm_type' => 'way',
                    'address' => [],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/company-information?domain=paper.id');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'query' => ['domain' => 'paper.id'],
                    'website_error' => null,
                    'domain_error' => null,
                    'location_error' => null,
                ],
            ])
            ->assertJsonPath('data.website.title', 'Paper.id')
            ->assertJsonPath('data.domain.domain', 'paper.id')
            ->assertJsonPath('data.location.display_name', 'Paper.id, Jakarta, Indonesia');
    }

    public function test_returns_partial_result_when_one_connector_fails(): void
    {
        Http::fake([
            'https://paper.id' => Http::response('<html><head><title>Paper.id</title></head></html>', 200),
            'rdap.org/*' => Http::response(null, 404),
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'display_name' => 'Paper.id, Jakarta, Indonesia',
                    'lat' => '-6.1751',
                    'lon' => '106.8650',
                    'importance' => 0.5,
                    'osm_type' => 'way',
                    'address' => [],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/company-information?domain=paper.id');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'domain' => null,
                ],
            ])
            ->assertJsonPath('data.domain_error.code', 'NOT_FOUND')
            ->assertJsonPath('data.website.title', 'Paper.id')
            ->assertJsonPath('data.website_error', null);
    }

    public function test_returns_validation_error_when_domain_param_missing(): void
    {
        $response = $this->getJson('/api/company-information');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR'],
            ]);
    }
}
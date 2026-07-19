<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DomainExtractTest extends TestCase
{
    public function test_returns_domain_data_for_valid_domain(): void
    {
        Http::fake([
            'rdap.org/*' => Http::response([
                'ldhName' => 'EXAMPLE.COM',
                'handle' => 'abc123',
                'status' => ['active'],
                'entities' => [
                    [
                        'roles' => ['registrar'],
                        'vcardArray' => ['vcard', [
                            ['fn', new \stdClass(), 'text', 'Registrar Contoh'],
                        ]],
                    ],
                ],
                'events' => [
                    ['eventAction' => 'registration', 'eventDate' => '2020-01-01T00:00:00Z'],
                    ['eventAction' => 'expiration', 'eventDate' => '2027-01-01T00:00:00Z'],
                ],
                'nameservers' => [
                    ['ldhName' => 'ns1.example.com'],
                    ['ldhName' => 'ns2.example.com'],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/extract/domain', [
            'domain' => 'example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'domain' => 'example.com',
                    'registrar' => 'Registrar Contoh',
                    'registered_at' => '2020-01-01T00:00:00Z',
                    'expired_at' => '2027-01-01T00:00:00Z',
                    'status' => ['active'],
                ],
            ])
            ->assertJsonPath('data.nameservers', ['ns1.example.com', 'ns2.example.com']);
    }

    public function test_returns_validation_error_when_domain_missing(): void
    {
        $response = $this->postJson('/api/extract/domain', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR'],
            ]);
    }

    public function test_returns_validation_error_when_domain_format_invalid(): void
    {
        $response = $this->postJson('/api/extract/domain', [
            'domain' => 'https://example.com',
        ]);

        $response->assertStatus(422);
    }

    public function test_returns_not_found_when_domain_unregistered(): void
    {
        Http::fake([
            'rdap.org/*' => Http::response(null, 404),
        ]);

        $response = $this->postJson('/api/extract/domain', [
            'domain' => 'domain-tidak-terdaftar-xyz.com',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND'],
            ]);
    }
}
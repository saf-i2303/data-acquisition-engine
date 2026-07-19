<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebsiteExtractTest extends TestCase
{
    public function test_returns_metadata_for_valid_url(): void
    {
        Http::fake([
            'https://example.com' => Http::response('
                <html>
                    <head>
                        <title>Contoh Judul</title>
                        <meta name="description" content="Contoh deskripsi">
                        <meta property="og:title" content="OG Title">
                    </head>
                    <body>
                        <a href="mailto:test@example.com">Email</a>
                    </body>
                </html>
            ', 200),
        ]);

        $response = $this->postJson('/api/extract/website', [
            'url' => 'https://example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'url' => 'https://example.com',
                    'title' => 'Contoh Judul',
                    'description' => 'Contoh deskripsi',
                ],
            ])
            ->assertJsonPath('data.emails', ['test@example.com']);
    }

    public function test_returns_validation_error_when_url_missing(): void
    {
        $response = $this->postJson('/api/extract/website', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR'],
            ]);
    }

    public function test_returns_validation_error_when_url_invalid_format(): void
    {
        $response = $this->postJson('/api/extract/website', [
            'url' => 'bukan-url-valid',
        ]);

        $response->assertStatus(422);
    }

    public function test_returns_error_when_website_unreachable(): void
    {
        Http::fake([
            'https://unreachable-site.test' => Http::response(null, 500),
        ]);

        $response = $this->postJson('/api/extract/website', [
            'url' => 'https://unreachable-site.test',
        ]);

        $response->assertStatus(502)
            ->assertJson([
                'success' => false,
                'error' => ['code' => 'EXTERNAL_SERVICE_ERROR'],
            ]);
    }
}
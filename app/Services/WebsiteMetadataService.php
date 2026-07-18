<?php

namespace App\Services;

use App\Exceptions\ExternalServiceException;
use App\Services\Contracts\WebsiteMetadataServiceInterface;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebsiteMetadataService implements WebsiteMetadataServiceInterface
{
    private const TIMEOUT_SECONDS = 10;

    private const SOCIAL_DOMAINS = [
        'facebook.com',
        'instagram.com',
        'twitter.com',
        'x.com',
        'linkedin.com',
        'youtube.com',
        'tiktok.com',
        't.me',
    ];

    public function extract(string $url): array
    {
        Log::info('Memulai ekstraksi metadata website', ['url' => $url]);

        $html = $this->fetchHtml($url);
        [, $xpath] = $this->parseHtml($html);

        $result = [
            'url' => $url,
            'title' => $this->extractTitle($xpath),
            'description' => $this->extractMetaByName($xpath, 'description'),
            'canonical' => $this->extractCanonical($xpath),
            'favicon' => $this->extractFavicon($xpath, $url),
            'emails' => $this->extractEmails($html, $xpath),
            'phones' => $this->extractPhones($html),
            'social_media' => $this->extractSocialMedia($xpath),
            'open_graph' => [
                'title' => $this->extractMetaByProperty($xpath, 'og:title'),
                'description' => $this->extractMetaByProperty($xpath, 'og:description'),
                'image' => $this->extractMetaByProperty($xpath, 'og:image'),
            ],
        ];

        Log::info('Ekstraksi metadata website selesai', [
            'url' => $url,
            'emails_found' => count($result['emails']),
            'phones_found' => count($result['phones']),
        ]);

        return $result;
    }

    private function fetchHtml(string $url): string
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; DataAcquisitionEngine/1.0)',
                ])
                ->get($url);
        } catch (Throwable $e) {
            Log::error('Gagal fetch HTML website', ['url' => $url, 'message' => $e->getMessage()]);
            throw new ExternalServiceException('website', $e->getMessage());
        }

        if ($response->failed()) {
            Log::warning('Website merespons dengan status gagal', [
                'url' => $url,
                'status' => $response->status(),
            ]);
            throw new ExternalServiceException('website', "status HTTP {$response->status()}");
        }

        return $response->body();
    }

    private function parseHtml(string $html): array
    {
        $dom = new DOMDocument();

        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        return [$dom, new DOMXPath($dom)];
    }

    private function extractTitle(DOMXPath $xpath): ?string
    {
        $node = $xpath->query('//title')->item(0);

        return $node ? trim($node->textContent) : null;
    }

    private function extractMetaByName(DOMXPath $xpath, string $name): ?string
    {
        /** @var DOMElement|null $node */
        $node = $xpath->query("//meta[@name='{$name}']")->item(0);

        return $node?->getAttribute('content') ?: null;
    }

    private function extractMetaByProperty(DOMXPath $xpath, string $property): ?string
    {
        /** @var DOMElement|null $node */
        $node = $xpath->query("//meta[@property='{$property}']")->item(0);

        return $node?->getAttribute('content') ?: null;
    }

    private function extractCanonical(DOMXPath $xpath): ?string
    {
        /** @var DOMElement|null $node */
        $node = $xpath->query("//link[@rel='canonical']")->item(0);

        return $node?->getAttribute('href') ?: null;
    }

    private function extractFavicon(DOMXPath $xpath, string $baseUrl): ?string
    {
        /** @var DOMElement|null $node */
        $node = $xpath->query("//link[@rel='icon' or @rel='shortcut icon']")->item(0);
        $href = $node?->getAttribute('href');

        if (!$href) {
            $parts = parse_url($baseUrl);
            if (!isset($parts['scheme'], $parts['host'])) {
                return null;
            }

            return "{$parts['scheme']}://{$parts['host']}/favicon.ico";
        }

        return $this->resolveUrl($baseUrl, $href);
    }

    /**
     * Gabungan 2 strategi: ambil email dari link mailto: (presisi, via XPath)
     * ditambah hasil sisir regex di seluruh teks HTML (jaring-jaring, buat
     * email yang ditulis polos tanpa mailto:).
     */
    private function extractEmails(string $html, DOMXPath $xpath): array
    {
        $fromMailto = [];

        foreach ($xpath->query('//a[starts-with(@href, "mailto:")]') as $anchor) {
            /** @var DOMElement $anchor */
            $fromMailto[] = str_replace('mailto:', '', $anchor->getAttribute('href'));
        }

        preg_match_all(
            '/[a-zA-Z0-9.\-_+]+@[a-zA-Z0-9\-]+\.[a-zA-Z]{2,}/',
            $html,
            $matches
        );

        return collect(array_merge($fromMailto, $matches[0] ?? []))
            ->map(fn ($email) => strtolower(trim($email)))
            ->unique()
            ->values()
            ->all();
    }

    private function extractPhones(string $html): array
    {
        preg_match_all(
            '/(?:\+?\d{1,3}[\s.-]?)?(?:\(\d{2,4}\)[\s.-]?)?\d{3,4}[\s.-]?\d{3,4}[\s.-]?\d{0,4}/',
            $html,
            $matches
        );

        $candidates = collect($matches[0] ?? [])
            ->map(fn ($phone) => trim($phone))
            ->filter(function ($phone) {
                if (preg_match('/\d+\.\d{3,}/', $phone)) {
                    return false;
                }

                $digits = preg_replace('/\D/', '', $phone);

                return strlen($digits) >= 8 && strlen($digits) <= 15;
            })
            ->map(function ($phone) {
                $normalized = preg_replace('/\D/', '', $phone);

                return [
                    'raw' => $phone,
                    'normalized' => $normalized,
                    'confidence' => $this->phoneConfidence($normalized),
                ];
            })
            ->unique('normalized')
            ->values();

        return [
            'confirmed' => $candidates
                ->where('confidence', 'high')
                ->pluck('normalized')
                ->values()
                ->all(),
            'possible' => $candidates
                ->where('confidence', 'low')
                ->values()
                ->all(),
        ];
    }

    private function phoneConfidence(string $digits): string
    {
        $isMobileFormat = str_starts_with($digits, '62') || str_starts_with($digits, '08');
        $isPlausibleLength = strlen($digits) >= 10 && strlen($digits) <= 14;

        return ($isMobileFormat && $isPlausibleLength) ? 'high' : 'low';
    }

    private function extractSocialMedia(DOMXPath $xpath): array
    {
        $links = [];

        foreach ($xpath->query('//a[@href]') as $anchor) {
            /** @var DOMElement $anchor */
            $href = $anchor->getAttribute('href');

            foreach (self::SOCIAL_DOMAINS as $domain) {
                if (str_contains($href, $domain)) {
                    $links[] = $href;
                    break;
                }
            }
        }

        return collect($links)->unique()->values()->all();
    }

    private function resolveUrl(string $baseUrl, string $relative): string
    {
        if (preg_match('#^https?://#i', $relative)) {
            return $relative;
        }

        $parts = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';

        if (str_starts_with($relative, '//')) {
            return "{$scheme}:{$relative}";
        }

        if (str_starts_with($relative, '/')) {
            return "{$scheme}://{$host}{$relative}";
        }

        return "{$scheme}://{$host}/" . ltrim($relative, './');
    }
}
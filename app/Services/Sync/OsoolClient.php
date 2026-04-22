<?php

namespace App\Services\Sync;

use Illuminate\Support\Facades\Http;

/**
 * HMAC-signed HTTP client for pulling from the Osool-B2G DWH sync API.
 *
 * Signature format (matches DwhHmacAuth on the source):
 *   HMAC-SHA256(secret, METHOD + "\n" + timestamp + "\n" + path + "\n" + sha256(body))
 */
class OsoolClient
{
    public function __construct(
        private string $baseUrl,
        private string $secret,
        private int $timeout = 30,
        private int $maxRetries = 3,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config('osool.base_url'),
            (string) config('osool.hmac_secret'),
            (int) config('osool.timeout', 30),
            (int) config('osool.max_retries', 3),
        );
    }

    public function get(string $path, array $query = []): array
    {
        if ($this->secret === '') {
            throw new \RuntimeException('OSOOL_HMAC_SECRET / DWH_HMAC_SECRET is not configured.');
        }

        $path      = '/' . ltrim($path, '/');
        $fullPath  = $query ? $path . '?' . http_build_query($query) : $path;
        $url       = $this->baseUrl . $fullPath;
        $timestamp = (string) time();
        $bodyHash  = hash('sha256', '');
        $payload   = implode("\n", ['GET', $timestamp, $fullPath, $bodyHash]);
        $signature = hash_hmac('sha256', $payload, $this->secret);

        $response = Http::timeout($this->timeout)
            ->retry($this->maxRetries, 500, throw: false)
            ->withHeaders([
                'Authorization' => 'HMAC ' . $signature,
                'X-Timestamp'   => $timestamp,
                'Accept'        => 'application/json',
            ])
            ->get($url);

        if ($response->failed()) {
            throw new \RuntimeException(
                "Osool API {$response->status()} on {$fullPath}: " . mb_substr((string) $response->body(), 0, 300)
            );
        }

        return (array) $response->json();
    }
}

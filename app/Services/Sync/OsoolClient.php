<?php

namespace App\Services\Sync;

use Illuminate\Http\Client\ConnectionException;
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

        $path     = '/' . ltrim($path, '/');
        $fullPath = $query ? $path . '?' . http_build_query($query) : $path;
        $url      = $this->baseUrl . $fullPath;

        $attempt = 0;
        while (true) {
            $attempt++;
            // Re-sign every attempt: HMAC timestamp must be fresh after a long Retry-After wait.
            $timestamp = (string) time();
            $payload   = implode("\n", ['GET', $timestamp, $fullPath, hash('sha256', '')]);
            $signature = hash_hmac('sha256', $payload, $this->secret);

            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'Authorization' => 'HMAC ' . $signature,
                        'X-Timestamp'   => $timestamp,
                        'Accept'        => 'application/json',
                    ])
                    ->get($url);
            } catch (ConnectionException $e) {
                if ($attempt > $this->maxRetries) throw $e;
                sleep(min(30, 2 ** $attempt));
                continue;
            }

            $status = $response->status();

            if ($status === 429 && $attempt <= $this->maxRetries) {
                sleep($this->parseRetryAfter($response->header('Retry-After')) ?? min(60, 2 ** $attempt));
                continue;
            }

            if ($status >= 500 && $attempt <= $this->maxRetries) {
                sleep(min(30, 2 ** $attempt));
                continue;
            }

            if ($response->failed()) {
                throw new \RuntimeException(
                    "Osool API {$status} on {$fullPath}: " . mb_substr((string) $response->body(), 0, 300)
                );
            }

            return (array) $response->json();
        }
    }

    private function parseRetryAfter(?string $header): ?int
    {
        if ($header === null || $header === '') return null;
        if (ctype_digit(trim($header))) return max(1, (int) $header);
        $when = strtotime($header);
        if ($when === false) return null;
        return max(1, $when - time());
    }
}

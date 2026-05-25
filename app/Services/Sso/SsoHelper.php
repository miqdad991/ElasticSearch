<?php

namespace App\Services\Sso;

/**
 * Sign and verify the short-lived SSO handoff token used between
 * Osool and this dashboard.
 *
 * Token format:  base64url(json_payload) . "." . hex(hmac_sha256)
 *
 * Payload claims:
 *   sub  - user_id (int)
 *   pid  - selected project_id (int|null)
 *   lang - locale (string)
 *   exp  - unix timestamp
 *   jti  - random uuid (replay protection)
 *   iss  - issuer ("osool")
 */
class SsoHelper
{
    public function __construct(private string $secret) {}

    public static function fromConfig(): self
    {
        $secret = (string) config('sso.secret', '');
        if ($secret === '') {
            throw new \RuntimeException('SSO_HMAC_SECRET is not configured.');
        }
        return new self($secret);
    }

    public function sign(array $claims): string
    {
        $payload   = self::b64url(json_encode($claims, JSON_UNESCAPED_UNICODE));
        $signature = hash_hmac('sha256', $payload, $this->secret);
        return $payload . '.' . $signature;
    }

    /**
     * @return array claims on success
     * @throws \RuntimeException on any validation failure
     */
    public function verify(string $token, ?string $expectedIssuer = 'osool'): array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            throw new \RuntimeException('Malformed SSO token.');
        }
        [$payload, $signature] = $parts;

        $expected = hash_hmac('sha256', $payload, $this->secret);
        if (!hash_equals($expected, $signature)) {
            throw new \RuntimeException('SSO token signature mismatch.');
        }

        $json = self::b64urlDecode($payload);
        $claims = json_decode($json, true);
        if (!is_array($claims)) {
            throw new \RuntimeException('SSO token payload is not valid JSON.');
        }

        if (!isset($claims['exp']) || time() >= (int) $claims['exp']) {
            throw new \RuntimeException('SSO token expired.');
        }

        if ($expectedIssuer !== null && ($claims['iss'] ?? null) !== $expectedIssuer) {
            throw new \RuntimeException('SSO token issuer mismatch.');
        }

        return $claims;
    }

    /**
     * Logout back-channel uses a separate, simpler envelope: the body
     * itself is signed and the signature travels in a header. No replay
     * cache — we accept a small timestamp window instead.
     */
    public function signBody(string $body): string
    {
        return hash_hmac('sha256', $body, $this->secret);
    }

    public function verifyBody(string $body, string $signature): bool
    {
        return hash_equals(hash_hmac('sha256', $body, $this->secret), $signature);
    }

    private static function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $encoded): string
    {
        $pad = strlen($encoded) % 4;
        if ($pad) $encoded .= str_repeat('=', 4 - $pad);
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
        if ($decoded === false) {
            throw new \RuntimeException('SSO token payload is not valid base64.');
        }
        return $decoded;
    }
}

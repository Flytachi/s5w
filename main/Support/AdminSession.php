<?php

declare(strict_types=1);

namespace Main\Support;

final class AdminSession
{
    private const int VERSION = 1;
    private const int NONCE_BYTES = 8;
    private const int SIGNATURE_BYTES = 32;

    public const int TTL = 86400;

    /**
     * @return array{token: string, expiresAt: int}
     */
    public static function issue(string $fingerprint): array
    {
        $expiresAt = time() + self::TTL;
        $body = pack('C', self::VERSION) . pack('N', $expiresAt) . random_bytes(self::NONCE_BYTES);

        return [
            'token' => self::encode($body . self::signature($body, $fingerprint)),
            'expiresAt' => $expiresAt,
        ];
    }

    public static function verify(?string $token, string $fingerprint): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $raw = base64_decode(strtr($token, '-_', '+/'), true);
        if ($raw === false || strlen($raw) !== 5 + self::NONCE_BYTES + self::SIGNATURE_BYTES) {
            return false;
        }

        $body = substr($raw, 0, -self::SIGNATURE_BYTES);
        $signature = substr($raw, -self::SIGNATURE_BYTES);

        if (!hash_equals(self::signature($body, $fingerprint), $signature)) {
            return false;
        }

        if (unpack('C', $body[0])[1] !== self::VERSION) {
            return false;
        }

        return unpack('N', substr($body, 1, 4))[1] > time();
    }

    private static function signature(string $body, string $fingerprint): string
    {
        $key = hash_hmac('sha256', 'winter/s5w/admin/v1|' . $fingerprint, (string) env('WINTER_KEY', ''), true);

        return hash_hmac('sha256', $body, $key, true);
    }

    private static function encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}

<?php

declare(strict_types=1);

namespace Main\Support;

use Flytachi\Winter\DI\Attribute\Singleton;

#[Singleton]
final class LinkSigner
{
    private const int VERSION = 1;
    private const int SIGNATURE_BYTES = 16;
    private const int FLAG_ATTACHMENT = 0b01;
    private const int FLAG_HAS_JTI = 0b10;

    public function sign(LinkPayload $payload): string
    {
        $body = pack('C', self::VERSION)
            . pack('J', $payload->fileId)
            . pack('N', $payload->expiresAt)
            . pack('C', $this->flags($payload))
            . pack('N', $payload->epoch);

        if ($payload->jti !== null) {
            $body .= pack('J', $payload->jti);
        }

        return $this->encode($body . $this->signature($body));
    }

    public function verify(string $token): ?LinkPayload
    {
        $raw = $this->decode($token);
        if ($raw === null || strlen($raw) < 18 + self::SIGNATURE_BYTES) {
            return null;
        }

        $body = substr($raw, 0, -self::SIGNATURE_BYTES);
        $signature = substr($raw, -self::SIGNATURE_BYTES);

        if (!hash_equals($this->signature($body), $signature)) {
            return null;
        }

        $version = unpack('C', $body[0])[1];
        if ($version !== self::VERSION) {
            return null;
        }

        $flags = unpack('C', $body[13])[1];
        $hasJti = ($flags & self::FLAG_HAS_JTI) !== 0;
        if (strlen($body) !== ($hasJti ? 26 : 18)) {
            return null;
        }

        return new LinkPayload(
            fileId: unpack('J', substr($body, 1, 8))[1],
            expiresAt: unpack('N', substr($body, 9, 4))[1],
            attachment: ($flags & self::FLAG_ATTACHMENT) !== 0,
            epoch: unpack('N', substr($body, 14, 4))[1],
            jti: $hasJti ? unpack('J', substr($body, 18, 8))[1] : null,
        );
    }

    private function flags(LinkPayload $payload): int
    {
        return ($payload->attachment ? self::FLAG_ATTACHMENT : 0)
            | ($payload->jti !== null ? self::FLAG_HAS_JTI : 0);
    }

    private function signature(string $body): string
    {
        $key = hash_hmac('sha256', 'winter/s5w/link/v1', (string) env('WINTER_KEY', ''), true);

        return substr(hash_hmac('sha256', $body, $key, true), 0, self::SIGNATURE_BYTES);
    }

    private function encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function decode(string $token): ?string
    {
        $raw = base64_decode(strtr($token, '-_', '+/'), true);

        return $raw === false ? null : $raw;
    }
}

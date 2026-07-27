<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Support;

/**
 * Keys for the write endpoints that accept `Idempotency-Key`.
 *
 * The API stores a successful response against the key for 24 hours and
 * replays it on repeat — so a retried booking cannot double-book, while a
 * failed attempt can safely be retried with the same key.
 */
final class IdempotencyKey
{
    /** A RFC 4122 v4 UUID, generated without pulling in a UUID library. */
    public static function generate(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return implode('-', [
            bin2hex(substr($bytes, 0, 4)),
            bin2hex(substr($bytes, 4, 2)),
            bin2hex(substr($bytes, 6, 2)),
            bin2hex(substr($bytes, 8, 2)),
            bin2hex(substr($bytes, 10, 6)),
        ]);
    }
}

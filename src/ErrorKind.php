<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk;

/**
 * Why a call did not succeed, in terms a caller can branch on.
 *
 * These case names are the SDK's cross-language contract: the TypeScript SDK
 * uses the same strings in its union type, so "handle Conflict by re-quoting"
 * is advice that survives a change of language. Do not rename a case without
 * changing every sibling SDK and CONVENTIONS.md.
 */
enum ErrorKind: string
{
    /** No HTTP response at all — DNS, TLS, timeout, refused. Synthesised as 503. */
    case ConnectionFailed = 'connection_failed';

    /** A response arrived but was not the API envelope. Synthesised as 502. */
    case MalformedResponse = 'malformed_response';

    /** 401 — credential missing, wrong, or revoked. */
    case Unauthorized = 'unauthorized';

    /** 403 — authenticated, but not allowed (missing OAuth scope, agency not API-enabled). */
    case Forbidden = 'forbidden';

    /** 404 — no such property/booking, or it belongs to another agency. */
    case NotFound = 'not_found';

    /**
     * 409 — a business refusal, not a bug: dates just taken, price moved,
     * nothing outstanding to pay, agency not on Stripe Connect. Usually the
     * right response is to re-read state and offer the caller a fresh choice.
     */
    case Conflict = 'conflict';

    /** 422 — the request was understood and rejected field by field. See errors(). */
    case ValidationFailed = 'validation_failed';

    /** 429 — rate limited (120/min reads, 20/min writes, per agency). See retryAfter(). */
    case RateLimited = 'rate_limited';

    /** Any other failure, including 5xx from upstream. */
    case ServerError = 'server_error';

    /** Classify a real HTTP status. Null for a 2xx, which is not an error by status. */
    public static function fromStatus(int $status): ?self
    {
        return match (true) {
            $status >= 200 && $status < 300 => null,
            $status === 401 => self::Unauthorized,
            $status === 403 => self::Forbidden,
            $status === 404 => self::NotFound,
            $status === 409 => self::Conflict,
            $status === 422 => self::ValidationFailed,
            $status === 429 => self::RateLimited,
            default => self::ServerError,
        };
    }

    /** @return class-string<Errors\ApiError> */
    public function exceptionClass(): string
    {
        return match ($this) {
            self::ConnectionFailed => Errors\ConnectionFailed::class,
            self::MalformedResponse => Errors\MalformedResponse::class,
            self::Unauthorized => Errors\Unauthorized::class,
            self::Forbidden => Errors\Forbidden::class,
            self::NotFound => Errors\NotFound::class,
            self::Conflict => Errors\Conflict::class,
            self::ValidationFailed => Errors\ValidationFailed::class,
            self::RateLimited => Errors\RateLimited::class,
            self::ServerError => Errors\ServerError::class,
        };
    }
}

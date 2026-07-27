<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk;

use IPropertyPro\Sdk\Auth\AuthStrategy;
use IPropertyPro\Sdk\Auth\BasicAuth;
use IPropertyPro\Sdk\Auth\NoAuth;

/**
 * Everything a Client needs to reach one agency's API.
 *
 * Immutable and explicit — no globals, no static configuration, so several
 * clients (different agencies, different environments) can coexist in one
 * process.
 */
final class Config
{
    public function __construct(
        public readonly string $baseUrl,
        public readonly AuthStrategy $auth = new NoAuth,
        public readonly int $timeout = 15,
        /** False only for local dev against a mkcert/self-signed certificate. */
        public readonly bool $verifyTls = true,
        public readonly string $userAgent = 'ipropertypro-php-sdk/1.x',
    ) {}

    /** The common case: an agency credential from the dashboard, sent as Basic. */
    public static function basic(
        string $baseUrl,
        string $clientId,
        string $clientSecret,
        int $timeout = 15,
        bool $verifyTls = true,
    ): self {
        return new self(
            baseUrl: $baseUrl,
            auth: new BasicAuth($clientId, $clientSecret),
            timeout: $timeout,
            verifyTls: $verifyTls,
        );
    }

    /** Absolute URL for an API path, tolerating slashes on either side. */
    public function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
    }
}

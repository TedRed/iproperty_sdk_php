<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Http;

/**
 * One outbound call, fully described and ready to send.
 *
 * A Transport receives this and nothing else: the path is already absolute
 * against the configured base URL, and the Authorization header is already on
 * it. That is deliberate — auth is a portable concern that belongs in the SDK
 * core (so an OAuth2 strategy plugs in once, not once per transport), while a
 * transport stays a dumb "send these bytes" adapter.
 */
final class ApiRequest
{
    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body  JSON-encoded by the transport; null means no body
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $query = [],
        public readonly ?array $body = null,
        public readonly array $headers = [],
        public readonly int $timeout = 15,
        public readonly bool $verifyTls = true,
    ) {}

    /** The URL with the query string appended, for transports that want one string. */
    public function fullUrl(): string
    {
        if ($this->query === []) {
            return $this->url;
        }

        $separator = str_contains($this->url, '?') ? '&' : '?';

        return $this->url.$separator.http_build_query($this->query);
    }
}

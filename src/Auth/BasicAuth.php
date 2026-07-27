<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Auth;

/**
 * The agency credential created in the dashboard under Agency settings →
 * Public API: a client_id (UUID) and an `ipp_sk_…` secret, sent as HTTP Basic.
 *
 * Basic bypasses scope checks server-side — it is the agency's own full-
 * privilege key — so it belongs on a server and never in a browser.
 */
final class BasicAuth implements AuthStrategy
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {}

    public function headers(): array
    {
        return ['Authorization' => 'Basic '.base64_encode($this->clientId.':'.$this->clientSecret)];
    }
}

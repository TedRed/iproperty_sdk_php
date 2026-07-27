<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Auth;

/**
 * How a request proves who it is.
 *
 * The API accepts two credentials against the same client_id/secret pair:
 * HTTP Basic (full privilege, what the white-label sites use) and OAuth2
 * client_credentials bearer tokens (scoped, one hour). Both reduce to "add
 * these headers", so one interface covers today and the scoped-token future.
 */
interface AuthStrategy
{
    /** @return array<string, string> headers to merge onto every request */
    public function headers(): array;
}

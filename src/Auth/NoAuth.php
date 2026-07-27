<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Auth;

/** For tests and for the handful of unauthenticated endpoints (`/up`). */
final class NoAuth implements AuthStrategy
{
    public function headers(): array
    {
        return [];
    }
}

<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk;

/**
 * Which businesses an agency runs: nightly stays, sale/long-let, or both.
 *
 * It rides in the /v1/agency payload and decides whether a consumer site
 * should filter listings by `kind` at all.
 */
enum AgencyMode: string
{
    case Hotel = 'hotel';
    case RealEstate = 'real_estate';
    case Hybrid = 'hybrid';

    /**
     * Read the mode out of an /v1/agency payload.
     *
     * Fails open to Hybrid — the same choice the dashboard makes. An API that
     * predates modes, or a payload that failed to load, must not blank a site's
     * listings; showing everything is the safe wrong answer.
     *
     * @param  array<string, mixed>|null  $branding
     */
    public static function fromPayload(?array $branding): self
    {
        return self::tryFrom((string) ($branding['mode'] ?? '')) ?? self::Hybrid;
    }

    /** True when the agency runs both lanes, and a site must choose one to lead with. */
    public function isHybrid(): bool
    {
        return $this === self::Hybrid;
    }
}

<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Resources;

use IPropertyPro\Sdk\ApiResponse;

/**
 * Who the credential belongs to.
 *
 * get() is the white-label payload — name, logo, banner, contact details, CDN
 * base and the operating `mode` a site keys its lane logic off. Cache it;
 * it changes rarely and every page needs it.
 */
final class Agency extends Resource
{
    public function get(): ApiResponse
    {
        return $this->client->get('/v1/agency');
    }

    /** The authenticated client itself — id, agency, granted scopes. */
    public function me(): ApiResponse
    {
        return $this->client->get('/v1/me');
    }
}

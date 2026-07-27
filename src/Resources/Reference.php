<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Resources;

use IPropertyPro\Sdk\ApiResponse;

/**
 * The lookup lists a search form is built from. Both are agency-scoped and
 * change rarely — good candidates for a cache in front of them.
 */
final class Reference extends Resource
{
    public function propertyTypes(): ApiResponse
    {
        return $this->client->get('/v1/property-types');
    }

    /** Countries, states, cities and towns the agency actually has listings in. */
    public function locations(): ApiResponse
    {
        return $this->client->get('/v1/locations');
    }
}

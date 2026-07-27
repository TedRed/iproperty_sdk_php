<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Tests\Contract;

use IPropertyPro\Sdk\Tests\TestCase;

/**
 * The SDK against the API's own OpenAPI document.
 *
 * spec/api.json is exported from iproperty_public_api (`artisan scramble:export`,
 * which generates it from the routes and controllers themselves) and committed
 * here, so it is the contract rather than a description of one. This test
 * checks the mapping in both directions: nothing the SDK calls has vanished,
 * and nothing the API publishes has been left unwrapped.
 *
 * When it fails, one of two things happened — the API changed and the SDK needs
 * to follow, or the spec was re-exported without the SDK being updated. Both
 * are exactly what should stop a release.
 */
class SpecCoverageTest extends TestCase
{
    /**
     * Every endpoint this SDK calls, as "METHOD /path" with the spec's own
     * parameter placeholders.
     *
     * @var array<string, string> endpoint => the SDK call that reaches it
     */
    private const ENDPOINTS = [
        'GET /v1/agency' => 'agency->get()',
        'GET /v1/me' => 'agency->me()',
        'GET /v1/property-types' => 'reference->propertyTypes()',
        'GET /v1/locations' => 'reference->locations()',
        'GET /v1/properties/search' => 'properties->search() and properties->searchMap()',
        'GET /v1/properties/{id}' => 'properties->get()',
        'GET /v1/properties/{id}/similar' => 'properties->similar()',
        'GET /v1/properties/{id}/nearby' => 'properties->nearby()',
        'GET /v1/properties/{id}/availability' => 'properties->availability()',
        'GET /v1/properties/{id}/quote' => 'properties->quote()',
        'POST /v1/properties/{id}/enquiries' => 'properties->enquire()',
        'POST /v1/properties/{id}/bookings' => 'properties->book()',
        'GET /v1/bookings/{id}/payment-schedule' => 'bookings->paymentSchedule()',
        'POST /v1/bookings/{id}/payments/session' => 'bookings->paymentSession()',
    ];

    /**
     * Documented endpoints the SDK deliberately does not wrap.
     *
     * The token endpoint belongs to an AuthStrategy rather than a resource —
     * it is how you obtain a credential, not something you do with one — and
     * no strategy needs it until scoped OAuth2 tokens ship.
     *
     * @var array<int, string>
     */
    private const NOT_WRAPPED = [
        'POST /oauth/token',
    ];

    /** @return array<string, array<int, string>> */
    private function specEndpoints(): array
    {
        $path = __DIR__.'/../../spec/api.json';

        $this->assertFileExists($path, 'Export it with `artisan scramble:export` in iproperty_public_api.');

        $spec = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $endpoints = [];
        foreach ($spec['paths'] as $path => $operations) {
            foreach (array_keys($operations) as $verb) {
                if (in_array(strtolower($verb), ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    $endpoints[] = strtoupper($verb).' '.$path;
                }
            }
        }

        sort($endpoints);

        return $endpoints;
    }

    public function test_the_spec_is_the_document_this_sdk_was_built_against(): void
    {
        $spec = json_decode((string) file_get_contents(__DIR__.'/../../spec/api.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertSame('IPropertyPro Public API', $spec['info']['title']);
        $this->assertSame(
            ['clientCredentialsBasic', 'oauth2ClientCredentials'],
            array_keys($spec['components']['securitySchemes']),
            'The API changed how clients authenticate — AuthStrategy needs to follow.',
        );
    }

    public function test_every_endpoint_the_sdk_calls_is_documented(): void
    {
        $documented = $this->specEndpoints();

        foreach (self::ENDPOINTS as $endpoint => $call) {
            $this->assertContains(
                $endpoint,
                $documented,
                "The SDK calls [{$endpoint}] via {$call}, but the API no longer documents it.",
            );
        }
    }

    public function test_every_documented_endpoint_is_reachable_from_the_sdk(): void
    {
        $covered = array_merge(array_keys(self::ENDPOINTS), self::NOT_WRAPPED);

        foreach ($this->specEndpoints() as $endpoint) {
            $this->assertContains(
                $endpoint,
                $covered,
                "The API documents [{$endpoint}] and no SDK method reaches it. Add one, or list it in NOT_WRAPPED with a reason.",
            );
        }
    }

    /**
     * Search is the only paginated endpoint, and its meta shape is repeated in
     * every SDK's documentation — worth pinning.
     */
    public function test_search_is_documented_with_the_filters_the_sdk_advertises(): void
    {
        $spec = json_decode((string) file_get_contents(__DIR__.'/../../spec/api.json'), true, 512, JSON_THROW_ON_ERROR);

        $parameters = array_column($spec['paths']['/v1/properties/search']['get']['parameters'] ?? [], 'name');

        $advertised = [
            'q', 'page', 'per_page', 'sort', 'kind', 'id_property_type', 'bedrooms', 'bathrooms',
            'transaction', 'min_price', 'max_price',
            // searchMap() is this endpoint too — `map` is what makes it one.
            'map', 'polygon',
        ];

        foreach ($advertised as $filter) {
            $this->assertContains($filter, $parameters, "Search no longer accepts [{$filter}].");
        }
    }
}

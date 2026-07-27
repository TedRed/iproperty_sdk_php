<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Tests\Unit;

use IPropertyPro\Sdk\AgencyMode;
use IPropertyPro\Sdk\ErrorKind;
use IPropertyPro\Sdk\Http\RawResponse;
use IPropertyPro\Sdk\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/** Each resource method hits the endpoint it claims to. */
class ResourcesTest extends TestCase
{
    public function test_it_maps_property_reads_onto_their_endpoints(): void
    {
        [$client, $transport] = $this->clientWith([
            'GET /v1/properties/search' => $this->fixture('properties.search'),
            'GET /v1/properties/11' => $this->fixture('property'),
            'GET /v1/properties/11/similar' => $this->fixture('properties.search'),
            'GET /v1/properties/11/availability' => $this->fixture('availability'),
            'GET /v1/properties/11/quote' => $this->fixture('quote'),
        ]);

        $client->properties->search(['q' => 'villa']);
        $client->properties->get(11);
        $client->properties->similar(11);
        $client->properties->availability(11, ['check_in' => '2026-09-01', 'check_out' => '2026-09-04']);
        $client->properties->quote(11, ['check_in' => '2026-09-01', 'check_out' => '2026-09-04', 'id_room_type' => 4]);

        $this->assertSame([
            'https://api.example.test/v1/properties/search',
            'https://api.example.test/v1/properties/11',
            'https://api.example.test/v1/properties/11/similar',
            'https://api.example.test/v1/properties/11/availability',
            'https://api.example.test/v1/properties/11/quote',
        ], array_map(fn ($request) => $request->url, $transport->sent()));
    }

    public function test_it_maps_lookups_and_agency_onto_their_endpoints(): void
    {
        [$client, $transport] = $this->clientWith([
            'GET /v1/agency' => $this->fixture('agency'),
            'GET /v1/me' => ['success' => true, 'data' => ['id_agency' => 1]],
            'GET /v1/property-types' => ['success' => true, 'data' => []],
            'GET /v1/locations' => ['success' => true, 'data' => []],
        ]);

        $client->agency->get();
        $client->agency->me();
        $client->reference->propertyTypes();
        $client->reference->locations();

        $this->assertSame([
            'https://api.example.test/v1/agency',
            'https://api.example.test/v1/me',
            'https://api.example.test/v1/property-types',
            'https://api.example.test/v1/locations',
        ], array_map(fn ($request) => $request->url, $transport->sent()));
    }

    public function test_booking_writes_carry_an_idempotency_key(): void
    {
        [$client, $transport] = $this->clientWith([
            'POST /v1/properties/11/bookings' => $this->fixture('booking.created'),
            'POST /v1/bookings/555/payments/session' => $this->fixture('payment.session'),
        ]);

        $client->properties->book(11, ['check_in' => '2026-09-01', 'check_out' => '2026-09-04']);
        $client->bookings->paymentSession(555);

        foreach ($transport->sent() as $request) {
            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                $request->headers['Idempotency-Key'],
            );
        }
    }

    /** A caller that owns the key — one per checkout attempt — keeps it. */
    public function test_a_supplied_idempotency_key_is_used_verbatim(): void
    {
        [$client, $transport] = $this->clientWith([
            'POST /v1/properties/11/bookings' => $this->fixture('booking.created'),
        ]);

        $client->properties->book(11, ['check_in' => '2026-09-01'], 'my-own-key');

        $this->assertSame('my-own-key', $transport->lastRequest()->headers['Idempotency-Key']);
    }

    public function test_a_taken_date_range_comes_back_as_a_conflict(): void
    {
        [$client] = $this->clientWith([
            'POST /v1/properties/11/bookings' => new RawResponse(
                409,
                json_encode($this->fixture('error.conflict')),
            ),
        ]);

        $response = $client->properties->book(11, ['check_in' => '2026-09-01']);

        $this->assertSame(ErrorKind::Conflict, $response->errorKind());
    }

    /** @return array<string, array{?array, AgencyMode}> */
    public static function brandingModes(): array
    {
        return [
            'hotel' => [['mode' => 'hotel'], AgencyMode::Hotel],
            'real estate' => [['mode' => 'real_estate'], AgencyMode::RealEstate],
            'hybrid' => [['mode' => 'hybrid'], AgencyMode::Hybrid],
            // Fails open: an older API or a failed branding call must not blank
            // a site's listings.
            'missing' => [[], AgencyMode::Hybrid],
            'unknown' => [['mode' => 'timeshare'], AgencyMode::Hybrid],
            'no payload at all' => [null, AgencyMode::Hybrid],
        ];
    }

    #[DataProvider('brandingModes')]
    public function test_it_reads_the_agency_mode_out_of_branding(?array $branding, AgencyMode $expected): void
    {
        $this->assertSame($expected, AgencyMode::fromPayload($branding));
    }
}

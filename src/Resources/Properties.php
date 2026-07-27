<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Resources;

use IPropertyPro\Sdk\ApiResponse;
use IPropertyPro\Sdk\Support\IdempotencyKey;

/**
 * Listings, and the things you do to one: enquire about it, check its dates,
 * price a stay, book it.
 *
 * Both verticals live behind this one resource because the API does — a
 * property is a house for sale or a hotel with room types depending on the
 * agency's mode, and `kind` is a filter rather than a separate endpoint.
 */
final class Properties extends Resource
{
    /**
     * Paginated search. Filters: q, page, per_page (1–100), sort
     * (relevance|name-asc|name-desc|beds-desc|baths-desc), id_country,
     * id_state, id_city, id_town, id_property_type, zip, reference, bedrooms,
     * bathrooms, kind (hotel|real_estate), transaction (sale|rent),
     * min_price, max_price, polygon.
     *
     * `transaction` picks the market: 'sale' for listings with an asking
     * price, 'rent' for a long-term monthly rent. Rows carry that market's
     * price, currency and price_mode ('monthly' for a rent, null for a sale).
     * The price bounds measure the same market's price, so they only bite
     * alongside a transaction.
     *
     * `polygon` is a drawn area: a JSON-encoded closed ring of [longitude,
     * latitude] pairs, at most 100 of them. It filters these paged results as
     * readily as it filters a map, so an area a visitor drew survives their
     * switch back to a list.
     *
     * Pagination arrives in meta(): { total, page, per_page, last_page }.
     *
     * @param  array<string, mixed>  $filters
     */
    public function search(array $filters = []): ApiResponse
    {
        return $this->client->get('/v1/properties/search', $filters);
    }

    /**
     * The same search, answered as map markers instead of a page.
     *
     * Takes every filter search() does. Rows are trimmed to what a pin needs —
     * id, name, reference, bedrooms, bathrooms, latitude, longitude, a single
     * `image`, and the transaction's price/currency/price_mode. Listings with
     * no coordinates cannot be plotted and are absent.
     *
     * There are no pages: a map shows an area, not page 3 of it. meta() is
     * { total, returned, capped, cap }, plus `bounds` ([[south, west],
     * [north, east]]) and a GeoJSON `boundary` when a location filter has the
     * geometry to frame the map with. When `capped` is true the area holds
     * more than `cap` listings and you are seeing `returned` of them — say so,
     * rather than letting the map imply that is all there is.
     *
     * @param  array<string, mixed>  $filters
     */
    public function searchMap(array $filters = []): ApiResponse
    {
        // Union, not merge: map mode is the whole point of the call, so a
        // stray map => false in the caller's filters cannot switch it off.
        return $this->client->get('/v1/properties/search', ['map' => 1] + $filters);
    }

    public function get(int $id): ApiResponse
    {
        return $this->client->get("/v1/properties/{$id}");
    }

    /** Other listings from the same agency that resemble this one. */
    public function similar(int $id): ApiResponse
    {
        return $this->client->get("/v1/properties/{$id}/similar");
    }

    /**
     * Which room types are free for a date range, and what they cost.
     *
     * @param  array<string, mixed>  $query  check_in, check_out (Y-m-d), guests (1–64)
     */
    public function availability(int $id, array $query = []): ApiResponse
    {
        return $this->client->get("/v1/properties/{$id}/availability", $query);
    }

    /**
     * Price one room type for a date range, taxes included.
     *
     * Money comes back as integer minor units — see CONVENTIONS.md; the SDK
     * never converts it.
     *
     * @param  array<string, mixed>  $query  check_in, check_out, id_room_type
     */
    public function quote(int $id, array $query = []): ApiResponse
    {
        return $this->client->get("/v1/properties/{$id}/quote", $query);
    }

    /**
     * Send a lead to the agency. 201 on success.
     *
     * @param  array<string, mixed>  $payload  name, email, message required; phone, type (sale|rent), source
     */
    public function enquire(int $id, array $payload): ApiResponse
    {
        return $this->client->post("/v1/properties/{$id}/enquiries", $payload);
    }

    /**
     * Request a booking. 201 on success; 409 when the dates went or the price
     * moved while the guest was deciding — re-quote and ask again.
     *
     * The idempotency key is generated when omitted, so a retried call cannot
     * create a second booking.
     *
     * @param  array<string, mixed>  $payload  check_in, check_out, id_room_type?, contact{}, guests[]
     */
    public function book(int $id, array $payload, ?string $idempotencyKey = null): ApiResponse
    {
        return $this->client->post("/v1/properties/{$id}/bookings", $payload, [
            'Idempotency-Key' => $idempotencyKey ?? IdempotencyKey::generate(),
        ]);
    }
}

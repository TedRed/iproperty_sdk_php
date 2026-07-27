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
     * bathrooms, kind (hotel|real_estate).
     *
     * Pagination arrives in meta(): { total, page, per_page, last_page }.
     *
     * @param  array<string, mixed>  $filters
     */
    public function search(array $filters = []): ApiResponse
    {
        return $this->client->get('/v1/properties/search', $filters);
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

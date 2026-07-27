<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Resources;

use IPropertyPro\Sdk\ApiResponse;
use IPropertyPro\Sdk\Support\IdempotencyKey;

/**
 * Money owed on a booking, and opening a Stripe payment for it.
 *
 * Charges run on the agency's own connected Stripe account, so what comes back
 * from session() — an intent client_secret, the connected account id and the
 * platform publishable key — is all publishable and safe to hand to a browser.
 * The API credential stays on your server.
 */
final class Bookings extends Resource
{
    /**
     * What is scheduled, paid and outstanding.
     *
     * Amounts are integer minor units, grouped per currency in `totals`.
     */
    public function paymentSchedule(int $id): ApiResponse
    {
        return $this->client->get("/v1/bookings/{$id}/payment-schedule");
    }

    /**
     * Open a payment for the earliest charge that is due, or a named one.
     *
     * 409 is a normal answer here — the agency may not take payments through
     * the platform, or nothing may be outstanding — so treat it as "no panel to
     * show" rather than an error.
     *
     * @param  array<string, mixed>  $payload  id_scheduled_charge? (defaults to the earliest due)
     */
    public function paymentSession(int $id, ?string $idempotencyKey = null, array $payload = []): ApiResponse
    {
        return $this->client->post("/v1/bookings/{$id}/payments/session", $payload, [
            'Idempotency-Key' => $idempotencyKey ?? IdempotencyKey::generate(),
        ]);
    }
}

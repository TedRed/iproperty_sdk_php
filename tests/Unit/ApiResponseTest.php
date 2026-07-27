<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Tests\Unit;

use IPropertyPro\Sdk\ApiResponse;
use IPropertyPro\Sdk\ErrorKind;
use IPropertyPro\Sdk\Errors;
use IPropertyPro\Sdk\Http\RawResponse;
use IPropertyPro\Sdk\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/** Reading a result, and turning it into an exception on request. */
class ApiResponseTest extends TestCase
{
    private function response(array $body, int $status = 200, array $headers = []): ApiResponse
    {
        return ApiResponse::fromRaw(new RawResponse($status, json_encode($body), $headers));
    }

    public function test_it_exposes_data_and_pagination(): void
    {
        $response = $this->response($this->fixture('properties.search'));

        $this->assertTrue($response->ok());
        $this->assertSame('Villa Alpha', $response->data()[0]['name']);
        $this->assertSame(['total' => 1, 'page' => 1, 'per_page' => 12, 'last_page' => 1], $response->meta());
    }

    /**
     * The envelope is what the white-label sites have always passed around, so
     * it has to survive the round trip byte for byte.
     */
    public function test_to_envelope_returns_the_body_with_the_status_merged_in(): void
    {
        $body = $this->fixture('properties.search');

        $this->assertSame($body + ['status' => 200], $this->response($body)->toEnvelope());
    }

    public function test_to_envelope_of_an_unreachable_api_matches_the_legacy_shape(): void
    {
        $this->assertSame(
            ['success' => false, 'message' => 'Service temporarily unavailable', 'status' => 503],
            ApiResponse::connectionFailed()->toEnvelope(),
        );
    }

    public function test_to_envelope_of_a_malformed_response_matches_the_legacy_shape(): void
    {
        $this->assertSame(
            ['success' => false, 'message' => 'Upstream error', 'status' => 502],
            ApiResponse::malformed(500)->toEnvelope(),
        );
    }

    /** @return array<string, array{int, ErrorKind}> */
    public static function statusKinds(): array
    {
        return [
            '401' => [401, ErrorKind::Unauthorized],
            '403' => [403, ErrorKind::Forbidden],
            '404' => [404, ErrorKind::NotFound],
            '409' => [409, ErrorKind::Conflict],
            '422' => [422, ErrorKind::ValidationFailed],
            '429' => [429, ErrorKind::RateLimited],
            '500' => [500, ErrorKind::ServerError],
        ];
    }

    #[DataProvider('statusKinds')]
    public function test_it_classifies_failures_by_status(int $status, ErrorKind $expected): void
    {
        $response = $this->response(['success' => false, 'message' => 'no'], $status);

        $this->assertSame($expected, $response->errorKind());
        $this->assertSame($status, $response->status());
    }

    public function test_a_successful_response_has_no_error_kind(): void
    {
        $this->assertNull($this->response($this->fixture('agency'))->errorKind());
    }

    /** 409 is a business answer, not a fault — it must not read as an outage. */
    public function test_a_conflict_keeps_its_message_and_status(): void
    {
        $response = $this->response($this->fixture('error.conflict'), 409);

        $this->assertSame(ErrorKind::Conflict, $response->errorKind());
        $this->assertSame('Those dates are no longer available', $response->message());
        $this->assertSame(409, $response->toEnvelope()['status']);
    }

    public function test_it_surfaces_field_errors_from_a_422(): void
    {
        $response = $this->response($this->fixture('error.validation'), 422);

        $this->assertSame(['The email field is required.'], $response->errors()['email']);
    }

    public function test_it_surfaces_retry_after_on_a_rate_limit(): void
    {
        $response = $this->response($this->fixture('error.rate-limited'), 429, ['Retry-After' => '30']);

        $this->assertSame(ErrorKind::RateLimited, $response->errorKind());
        $this->assertSame(30, $response->retryAfter());
    }

    public function test_it_reports_an_idempotent_replay(): void
    {
        $replayed = $this->response($this->fixture('booking.created'), 201, ['Idempotent-Replayed' => 'true']);
        $fresh = $this->response($this->fixture('booking.created'), 201);

        $this->assertTrue($replayed->idempotentReplay());
        $this->assertFalse($fresh->idempotentReplay());
    }

    public function test_data_or_fail_returns_the_payload_on_success(): void
    {
        $this->assertSame('Bayfront Estates', $this->response($this->fixture('agency'))->dataOrFail()['name']);
    }

    public function test_data_or_fail_throws_the_exception_matching_the_kind(): void
    {
        $response = $this->response($this->fixture('error.conflict'), 409);

        $this->expectException(Errors\Conflict::class);
        $this->expectExceptionMessage('Those dates are no longer available');

        $response->dataOrFail();
    }

    public function test_a_thrown_error_carries_the_response_back(): void
    {
        $response = $this->response($this->fixture('error.validation'), 422);

        try {
            $response->dataOrFail();
            $this->fail('Expected a ValidationFailed exception');
        } catch (Errors\ValidationFailed $e) {
            $this->assertSame(ErrorKind::ValidationFailed, $e->kind());
            $this->assertSame(422, $e->status());
            $this->assertSame($response, $e->response());
        }
    }

    /**
     * `success: false` with a 2xx should not slip through as a success — it is
     * still a refusal, just an oddly-statused one.
     */
    public function test_a_failure_body_with_a_2xx_status_is_still_a_failure(): void
    {
        $response = $this->response(['success' => false, 'message' => 'no'], 200);

        $this->assertTrue($response->failed());
        $this->assertSame(ErrorKind::ServerError, $response->errorKind());
    }
}

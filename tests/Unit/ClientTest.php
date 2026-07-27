<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Tests\Unit;

use IPropertyPro\Sdk\Errors\ConnectionFailed;
use IPropertyPro\Sdk\Http\RawResponse;
use IPropertyPro\Sdk\Tests\TestCase;

/** How a call is assembled and sent. */
class ClientTest extends TestCase
{
    public function test_it_sends_the_agency_credential_as_basic_auth(): void
    {
        [$client, $transport] = $this->clientWith(['GET /v1/agency' => $this->fixture('agency')]);

        $client->agency->get();

        $this->assertSame(
            'Basic '.base64_encode('client-id:ipp_sk_secret'),
            $transport->lastRequest()->headers['Authorization'],
        );
    }

    public function test_it_resolves_paths_against_the_base_url(): void
    {
        [$client, $transport] = $this->clientWith(['GET /v1/properties/11' => $this->fixture('property')]);

        $client->properties->get(11);

        $this->assertSame('https://api.example.test/v1/properties/11', $transport->lastRequest()->url);
    }

    public function test_it_puts_filters_in_the_query_string(): void
    {
        [$client, $transport] = $this->clientWith(['GET /v1/properties/search' => $this->fixture('properties.search')]);

        $client->properties->search(['q' => 'villa', 'per_page' => 12]);

        $request = $transport->lastRequest();
        $this->assertSame(['q' => 'villa', 'per_page' => 12], $request->query);
        $this->assertStringContainsString('q=villa&per_page=12', $request->fullUrl());
    }

    /**
     * A null filter is an absent filter. Serialised it would become `bedrooms=`
     * and read upstream as a real constraint, quietly emptying a search.
     */
    public function test_null_filters_are_dropped_rather_than_sent_empty(): void
    {
        [$client, $transport] = $this->clientWith(['GET /v1/properties/search' => $this->fixture('properties.search')]);

        $client->properties->search(['q' => 'villa', 'kind' => null, 'bedrooms' => 0]);

        $this->assertSame(['q' => 'villa', 'bedrooms' => 0], $transport->lastRequest()->query);
    }

    public function test_a_post_carries_a_json_body(): void
    {
        [$client, $transport] = $this->clientWith(['POST /v1/properties/11/enquiries' => $this->fixture('agency')]);

        $client->properties->enquire(11, ['name' => 'Sam', 'email' => 'sam@example.test', 'message' => 'Hello']);

        $request = $transport->lastRequest();
        $this->assertSame('POST', $request->method);
        $this->assertSame('Sam', $request->body['name']);
        $this->assertSame('application/json', $request->headers['Content-Type']);
    }

    public function test_reads_carry_no_content_type(): void
    {
        [$client, $transport] = $this->clientWith(['GET /v1/agency' => $this->fixture('agency')]);

        $client->agency->get();

        $this->assertArrayNotHasKey('Content-Type', $transport->lastRequest()->headers);
    }

    public function test_it_passes_the_configured_timeout_and_tls_setting_through(): void
    {
        [$client, $transport] = $this->clientWith(['GET /v1/agency' => $this->fixture('agency')]);

        $client->agency->get();

        $this->assertSame(15, $transport->lastRequest()->timeout);
        $this->assertTrue($transport->lastRequest()->verifyTls);
    }

    /**
     * An unreachable API is an expected state for a public website, so it comes
     * back as a failed result rather than an exception the caller must catch.
     */
    public function test_an_unreachable_api_becomes_a_503_result_not_an_exception(): void
    {
        [$client] = $this->clientWith(['GET /v1/agency' => new ConnectionFailed('Connection refused')]);

        $response = $client->agency->get();

        $this->assertTrue($response->failed());
        $this->assertSame(503, $response->status());
        $this->assertSame('Service temporarily unavailable', $response->message());
    }

    public function test_a_non_envelope_body_becomes_a_502_result(): void
    {
        [$client] = $this->clientWith([
            'GET /v1/agency' => new RawResponse(200, '<html>Bad Gateway</html>'),
        ]);

        $response = $client->agency->get();

        $this->assertTrue($response->failed());
        $this->assertSame(502, $response->status());
        $this->assertSame('Upstream error', $response->message());
    }
}

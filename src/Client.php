<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk;

use IPropertyPro\Sdk\Errors\ConnectionFailed;
use IPropertyPro\Sdk\Http\ApiRequest;
use IPropertyPro\Sdk\Http\CurlTransport;
use IPropertyPro\Sdk\Http\Transport;
use IPropertyPro\Sdk\Resources\Agency;
use IPropertyPro\Sdk\Resources\Bookings;
use IPropertyPro\Sdk\Resources\Properties;
use IPropertyPro\Sdk\Resources\Reference;

/**
 * The entry point: one client per agency credential.
 *
 *     $client = new Client(Config::basic('https://api.ipropertypro.net', $id, $secret));
 *     $result = $client->properties->search(['q' => 'villa', 'per_page' => 12]);
 *
 * Calls are grouped by resource rather than flattened onto the client, so the
 * surface reads the same here as it will in the TypeScript SDK.
 */
final class Client
{
    public readonly Properties $properties;

    public readonly Bookings $bookings;

    public readonly Agency $agency;

    public readonly Reference $reference;

    private readonly Transport $transport;

    public function __construct(
        private readonly Config $config,
        ?Transport $transport = null,
    ) {
        $this->transport = $transport ?? new CurlTransport;

        $this->properties = new Properties($this);
        $this->bookings = new Bookings($this);
        $this->agency = new Agency($this);
        $this->reference = new Reference($this);
    }

    public function config(): Config
    {
        return $this->config;
    }

    /** @param array<string, mixed> $query */
    public function get(string $path, array $query = []): ApiResponse
    {
        return $this->send('GET', $path, query: $query);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function post(string $path, array $payload = [], array $headers = []): ApiResponse
    {
        return $this->send('POST', $path, body: $payload, headers: $headers);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     * @param  array<string, string>  $headers
     */
    public function send(string $method, string $path, array $query = [], ?array $body = null, array $headers = []): ApiResponse
    {
        $request = new ApiRequest(
            method: $method,
            url: $this->config->url($path),
            // Nulls would serialise as empty strings and become real filters
            // upstream; an absent option must stay absent.
            query: array_filter($query, static fn ($value) => $value !== null),
            body: $body,
            headers: $this->headers($body !== null) + $headers,
            timeout: $this->config->timeout,
            verifyTls: $this->config->verifyTls,
        );

        try {
            return ApiResponse::fromRaw($this->transport->send($request));
        } catch (ConnectionFailed $e) {
            // Deliberately swallowed: an unreachable API is an expected state
            // for a public website, and a caller that wants it as an exception
            // asks via dataOrFail(). The cause is kept on reason() so a host
            // app can still log why.
            return ApiResponse::connectionFailed($e->getMessage());
        }
    }

    /** @return array<string, string> */
    private function headers(bool $hasBody): array
    {
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => $this->config->userAgent,
        ] + $this->config->auth->headers();

        if ($hasBody) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }
}

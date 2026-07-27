<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Http;

use IPropertyPro\Sdk\Errors\ConnectionFailed;

/**
 * Canned responses for tests, with no HTTP and no framework.
 *
 * Every sibling SDK ships one of these — it is what lets the shared fixtures in
 * spec/fixtures be exercised identically in each language.
 *
 * Routes are matched on "METHOD /path", `*` wildcards allowed:
 *
 *     new FakeTransport([
 *         'GET /v1/agency' => ['success' => true, 'data' => [...]],
 *         'GET /v1/properties/*' => new RawResponse(404, '{"success":false}'),
 *         '*' => ['success' => true],
 *     ]);
 */
final class FakeTransport implements Transport
{
    /** @var array<int, ApiRequest> */
    private array $sent = [];

    /** @param array<string, RawResponse|array<string, mixed>|\Throwable> $routes */
    public function __construct(private array $routes = []) {}

    /** @param RawResponse|array<string, mixed>|\Throwable $response */
    public function stub(string $pattern, RawResponse|array|\Throwable $response): self
    {
        $this->routes[$pattern] = $response;

        return $this;
    }

    public function send(ApiRequest $request): RawResponse
    {
        $this->sent[] = $request;

        $path = (string) parse_url($request->url, PHP_URL_PATH);
        $subject = $request->method.' '.$path;

        foreach ($this->routes as $pattern => $response) {
            if (! $this->matches($pattern, $subject, $path)) {
                continue;
            }

            if ($response instanceof \Throwable) {
                throw $response;
            }

            return $response instanceof RawResponse
                ? $response
                : new RawResponse(200, json_encode($response, JSON_THROW_ON_ERROR), ['Content-Type' => 'application/json']);
        }

        // An unstubbed call is a test bug, not a network event — say so loudly
        // rather than letting it look like a real outage.
        throw new ConnectionFailed("No fake response stubbed for [{$subject}]");
    }

    /** @return array<int, ApiRequest> every request in the order it was sent */
    public function sent(): array
    {
        return $this->sent;
    }

    public function lastRequest(): ?ApiRequest
    {
        return $this->sent === [] ? null : $this->sent[array_key_last($this->sent)];
    }

    private function matches(string $pattern, string $subject, string $path): bool
    {
        // A pattern with no verb matches the path alone.
        $target = str_contains($pattern, ' ') ? $subject : $path;

        return fnmatch($pattern, $target);
    }
}

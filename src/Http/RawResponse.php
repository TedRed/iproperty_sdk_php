<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Http;

/**
 * What a Transport hands back: status, headers, undecoded body.
 *
 * Nothing here knows about the API's envelope — interpretation happens one
 * layer up, in ApiResponse, so every transport stays interchangeable.
 */
final class RawResponse
{
    /** @var array<string, string> header names lower-cased */
    private readonly array $headers;

    /** @param array<string, string|array<int, string>> $headers */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        array $headers = [],
    ) {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower((string) $name)] = is_array($value) ? (string) reset($value) : (string) $value;
        }

        $this->headers = $normalized;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    /** The decoded body, or null when it is not valid JSON. */
    public function json(): mixed
    {
        if ($this->body === '') {
            return null;
        }

        try {
            return json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }
}

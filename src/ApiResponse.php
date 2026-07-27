<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk;

use IPropertyPro\Sdk\Http\RawResponse;

/**
 * The result of one call — success or failure, never an exception.
 *
 * The API encodes failure in-band (`{ success: false, message }`) and treats
 * 409 as a legitimate answer rather than a fault, so a result object models it
 * more honestly than exceptions do, and translates to a discriminated union in
 * TypeScript. Callers who want exceptions ask for them per call with
 * dataOrFail(); callers who want the old array envelope call toEnvelope().
 *
 * Transport-level failures are folded in here too, with a synthetic status, so
 * that "the network died" and "the API said no" are handled the same way.
 */
final class ApiResponse
{
    /** @param array<string, mixed> $body the decoded envelope, verbatim */
    private function __construct(
        private readonly array $body,
        private readonly int $status,
        private readonly ?ErrorKind $errorKind,
        private readonly ?RawResponse $raw = null,
        private readonly ?string $reason = null,
    ) {}

    public static function fromRaw(RawResponse $raw): self
    {
        $body = $raw->json();

        // Not the envelope: a proxy error page, an HTML crash dump, empty body.
        // There is nothing here a caller can act on, so it is one kind of error
        // regardless of what status came with it.
        if (! is_array($body) || ! array_key_exists('success', $body)) {
            return self::malformed($raw->status, $raw);
        }

        $success = (bool) $body['success'];
        $kind = $success
            ? null
            : (ErrorKind::fromStatus($raw->status) ?? ErrorKind::ServerError);

        return new self($body, $raw->status, $kind, $raw);
    }

    /**
     * @param  string|null  $reason  the underlying transport error, for logs —
     *                               the caller-facing message stays generic
     */
    public static function connectionFailed(?string $reason = null, string $message = 'Service temporarily unavailable'): self
    {
        return new self(
            ['success' => false, 'message' => $message],
            503,
            ErrorKind::ConnectionFailed,
            reason: $reason,
        );
    }

    public static function malformed(int $status, ?RawResponse $raw = null): self
    {
        return new self(
            ['success' => false, 'message' => 'Upstream error'],
            502,
            ErrorKind::MalformedResponse,
            $raw,
            reason: "Upstream responded {$status} with a body that was not the API envelope",
        );
    }

    // ---- reading the result -------------------------------------------------

    public function ok(): bool
    {
        return (bool) ($this->body['success'] ?? false);
    }

    public function failed(): bool
    {
        return ! $this->ok();
    }

    /** HTTP status, or a synthetic one: 503 unreachable, 502 malformed. */
    public function status(): int
    {
        return $this->status;
    }

    public function data(): mixed
    {
        return $this->body['data'] ?? null;
    }

    /**
     * Pagination for search: { total, page, per_page, last_page }. Wire names
     * are kept as-is so the shape is the same in every SDK.
     *
     * @return array<string, mixed>|null
     */
    public function meta(): ?array
    {
        $meta = $this->body['meta'] ?? null;

        return is_array($meta) ? $meta : null;
    }

    public function message(): ?string
    {
        $message = $this->body['message'] ?? null;

        return is_string($message) ? $message : null;
    }

    /**
     * Field-level errors from a 422.
     *
     * @return array<string, array<int, string>>|null
     */
    public function errors(): ?array
    {
        $errors = $this->body['errors'] ?? null;

        return is_array($errors) ? $errors : null;
    }

    public function errorKind(): ?ErrorKind
    {
        return $this->errorKind;
    }

    /**
     * Why this failed, in engineering terms rather than caller-facing ones —
     * the curl error, or what arrived instead of an envelope. For logs.
     */
    public function reason(): ?string
    {
        return $this->reason;
    }

    /**
     * The status the transport actually saw. Differs from status() only when a
     * response was unusable and got a synthetic 502.
     */
    public function rawStatus(): ?int
    {
        return $this->raw?->status;
    }

    /** True when the API replayed a stored result for the same Idempotency-Key. */
    public function idempotentReplay(): bool
    {
        return $this->raw?->header('Idempotent-Replayed') === 'true';
    }

    /** Seconds to wait before retrying, from a 429's Retry-After. */
    public function retryAfter(): ?int
    {
        $value = $this->raw?->header('Retry-After');

        return is_numeric($value) ? (int) $value : null;
    }

    public function header(string $name): ?string
    {
        return $this->raw?->header($name);
    }

    // ---- getting the payload out --------------------------------------------

    /**
     * The envelope as a plain array, `status` merged in — the shape the
     * white-label sites have always passed around.
     *
     * @return array{success: bool, data?: mixed, meta?: array, message?: string, status: int}
     */
    public function toEnvelope(): array
    {
        // `+` and not array_merge: a body that carries its own `status` keeps it,
        // exactly as the sites' original normalize() behaved.
        return $this->body + ['status' => $this->status];
    }

    /**
     * The payload, or an exception matching errorKind().
     *
     * The opt-in half of the error model: one call, one decision, no
     * client-wide "throwing mode" to reason about.
     *
     * @throws Errors\ApiError
     */
    public function dataOrFail(): mixed
    {
        if ($this->ok()) {
            return $this->data();
        }

        $kind = $this->errorKind ?? ErrorKind::ServerError;
        $class = $kind->exceptionClass();

        throw new $class($this->message() ?? 'The API request failed', $this);
    }
}

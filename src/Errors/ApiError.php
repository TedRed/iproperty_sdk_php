<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Errors;

use IPropertyPro\Sdk\ApiResponse;
use IPropertyPro\Sdk\ErrorKind;

/**
 * Base for every SDK exception.
 *
 * Nothing in the SDK throws these on its own — resource methods always return
 * an ApiResponse. You get one only by asking, via ApiResponse::dataOrFail(),
 * which is how callers who prefer try/catch opt in one call at a time. The one
 * exception is ConnectionFailed, which a Transport throws inward and the
 * Client immediately converts into a failed ApiResponse.
 */
abstract class ApiError extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?ApiResponse $response = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $response?->status() ?? 0, $previous);
    }

    /** The full response that caused this, when there was one. */
    public function response(): ?ApiResponse
    {
        return $this->response;
    }

    public function kind(): ?ErrorKind
    {
        return $this->response?->errorKind();
    }

    /** HTTP status, real or synthesised (503 unreachable, 502 malformed). */
    public function status(): int
    {
        return $this->response?->status() ?? 0;
    }
}

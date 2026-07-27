<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Errors;

/** The API could not be reached at all: DNS, TLS, timeout or refused connection. Thrown by a Transport, caught by the Client. */
final class ConnectionFailed extends ApiError {}

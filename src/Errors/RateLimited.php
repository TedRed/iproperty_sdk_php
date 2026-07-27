<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Errors;

/** 429. Over the agency's budget (120/min reads, 20/min writes); retryAfter() says how long to wait. */
final class RateLimited extends ApiError {}

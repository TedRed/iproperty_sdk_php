<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Errors;

/** 403. Authenticated but not permitted: an OAuth token missing a scope, or an agency not configured for API access. */
final class Forbidden extends ApiError {}

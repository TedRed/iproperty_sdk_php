<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Errors;

/** 404. No such record, or it belongs to a different agency — the API does not distinguish the two. */
final class NotFound extends ApiError {}

<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Errors;

/** 401. The client_id/secret is missing, wrong, or the credential was revoked in the dashboard. */
final class Unauthorized extends ApiError {}

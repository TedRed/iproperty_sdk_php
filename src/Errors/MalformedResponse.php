<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Errors;

/** A response arrived but was not the { success, ... } envelope — usually a proxy error page or an upstream crash. */
final class MalformedResponse extends ApiError {}

<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Errors;

/** 409. A business refusal rather than a fault: dates just taken, price moved, nothing outstanding to pay. */
final class Conflict extends ApiError {}

<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Resources;

use IPropertyPro\Sdk\Client;

abstract class Resource
{
    public function __construct(protected readonly Client $client) {}
}

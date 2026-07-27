<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Tests;

use IPropertyPro\Sdk\Client;
use IPropertyPro\Sdk\Config;
use IPropertyPro\Sdk\Http\FakeTransport;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A client wired to canned responses.
     *
     * @param  array<string, mixed>  $routes  see FakeTransport
     * @return array{0: Client, 1: FakeTransport}
     */
    protected function clientWith(array $routes): array
    {
        $transport = new FakeTransport($routes);

        $client = new Client(
            Config::basic('https://api.example.test', 'client-id', 'ipp_sk_secret'),
            $transport,
        );

        return [$client, $transport];
    }

    /**
     * A response fixture from spec/fixtures — the same files every sibling SDK
     * tests against, so a contract change breaks all of them at once.
     *
     * @return array<string, mixed>
     */
    protected function fixture(string $name): array
    {
        $path = __DIR__.'/../spec/fixtures/'.$name.'.json';

        if (! is_file($path)) {
            $this->fail("Missing fixture [{$name}] at {$path}");
        }

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }
}

# ipropertypro/php-sdk

PHP client for the [IPropertyPro Public API](https://api.ipropertypro.net) — the
customer-facing REST API for property, availability, enquiry and booking data,
scoped to one agency's credential.

Framework-agnostic and dependency-free. Laravel applications should install
[`ipropertypro/laravel-sdk`](https://github.com/TedRed/iproperty_sdk_laravel)
instead, which wires this up to the framework's HTTP client, config and cache.

## Install

```bash
composer require ipropertypro/php-sdk
```

## Use

Create a credential in the dashboard under **Agency settings → Public API**. It
is a full-privilege key for the agency, so it belongs on a server and never in a
browser.

```php
use IPropertyPro\Sdk\Client;
use IPropertyPro\Sdk\Config;

$client = new Client(Config::basic(
    'https://api.ipropertypro.net',
    $clientId,          // UUID
    $clientSecret,      // ipp_sk_…
));

$results = $client->properties->search(['q' => 'villa', 'per_page' => 12]);

foreach ($results->data() as $property) {
    echo $property['name'];
}

echo $results->meta()['total'];
```

### Results, not exceptions

Every call returns an `ApiResponse`. Nothing throws — an unreachable API and a
refused booking are both ordinary states for a public website.

```php
$booking = $client->properties->book($id, $payload);

if ($booking->ok()) {
    return redirect()->route('booked', $booking->data()['id_booking']);
}

// 409 is an answer, not a fault: the dates went, or the price moved.
if ($booking->errorKind() === ErrorKind::Conflict) {
    return back()->withErrors($booking->message());
}
```

Prefer exceptions for a given call? Ask for them:

```php
$property = $client->properties->get($id)->dataOrFail();   // throws Errors\NotFound, etc.
```

### Writes are idempotent

`book()` and `paymentSession()` generate an `Idempotency-Key` when you do not
supply one, so a retry cannot create a second booking. Own the key yourself when
the retry spans a user action — one per checkout attempt:

```php
$client->properties->book($id, $payload, $checkoutAttemptId);
```

### Testing

`FakeTransport` gives you canned responses with no HTTP and no framework:

```php
use IPropertyPro\Sdk\Http\FakeTransport;
use IPropertyPro\Sdk\Http\RawResponse;

$transport = new FakeTransport([
    'GET /v1/properties/search' => ['success' => true, 'data' => [], 'meta' => ['total' => 0]],
    'POST /v1/properties/*/bookings' => new RawResponse(409, '{"success":false,"message":"Taken"}'),
]);

$client = new Client($config, $transport);
```

`$transport->sent()` returns every request in order, for asserting what went out.

### Bring your own HTTP client

Implement `Http\Transport` to route calls through whatever your app already
uses — that is how retries, logging and test interception stay yours rather than
being reinvented here. The bundled `CurlTransport` is only the default.

## Conventions

[`CONVENTIONS.md`](CONVENTIONS.md) documents the rules this SDK follows and that
sibling SDKs in other languages must follow too: resource grouping, the auth
strategy seam, idempotency, pagination, the `ErrorKind` taxonomy, money as
integer minor units, and the retry policy.

## The spec

`spec/api.json` is the API's own OpenAPI 3.1 document, exported with
`artisan scramble:export` in `iproperty_public_api` and committed here as the
contract. `spec/fixtures/*.json` are the shared response fixtures used by this
SDK's tests, the Laravel bridge's tests, and any sibling SDK.

Re-export it whenever the API changes; `tests/Contract/SpecCoverageTest.php`
fails if the SDK and the spec drift apart in either direction.

## Development

```bash
composer install
composer test      # phpunit
composer lint      # pint --test
```

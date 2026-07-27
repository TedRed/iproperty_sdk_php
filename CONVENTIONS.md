# SDK conventions

Rules every IPropertyPro SDK follows, whatever the language. The PHP client in
this repo is the reference implementation; a TypeScript one is the next
candidate. Read this before adding a method here, and before starting a sibling.

The point is not uniform code — it is that advice transfers. "Handle a
`Conflict` by re-quoting and asking the guest again" should be true whether
someone is reading the PHP docs or the TS ones.

## 1. Construction is explicit

One client per credential, configured by value, no globals and no static setup —
so two agencies, or a live and a sandbox connection, can coexist in one process.

```php
$client = new Client(Config::basic('https://api.ipropertypro.net', $clientId, $clientSecret));
```

Config carries `baseUrl`, an auth strategy, `timeout` (15s default), `verifyTls`
(false only for local mkcert dev) and a `userAgent`. Nothing else.

## 2. Calls are grouped by resource

`client.<resource>.<verb>()`, resources named as plural nouns, methods as
camelCase verbs in every language:

| Resource     | Methods                                                              |
| ------------ | -------------------------------------------------------------------- |
| `properties` | `search`, `get`, `similar`, `availability`, `quote`, `enquire`, `book` |
| `bookings`   | `paymentSchedule`, `paymentSession`                                   |
| `agency`     | `get`, `me`                                                           |
| `reference`  | `propertyTypes`, `locations`                                          |

Grouping mirrors the API's own shape and keeps the surface legible as it grows.
Do not flatten these onto the client.

## 3. Auth is a strategy, not a parameter

`AuthStrategy` returns headers. `BasicAuth` (the agency credential from the
dashboard — full privilege, server-side only) is what the white-label sites use.
The OAuth2 client_credentials flow — `POST /oauth/token`, one-hour tokens,
default scope `properties:read` only — plugs in as a second strategy without
touching resources or transports. Keep the interface name.

## 4. Writes are idempotent by default

`book()` and `paymentSession()` take an optional `idempotencyKey` and generate a
UUIDv4 when it is omitted, so a retry cannot double-book. The API replays a
stored success for 24 hours; `ApiResponse.idempotentReplay()` reports when it
did. Failed attempts may be retried with the same key.

Own the key yourself when the retry spans a user action — one key per checkout
attempt, not per HTTP call.

## 5. Pagination is passed through, not wrapped

Search returns `meta { total, page, per_page, last_page }`. Wire names stay
snake_case in every language so the shape is quotable across SDKs. No auto-
paginating iterators — callers page explicitly.

## 6. Errors are results first, exceptions on request

The API encodes failure in-band and treats 409 as a legitimate answer, so every
call returns a result object and resource methods never throw. Callers who want
exceptions ask per call (`dataOrFail()` in PHP, `.orThrow()` in TS) — never a
client-wide throwing mode.

`ErrorKind` case names are a hard contract across languages:

| Kind                | Status  | Means                                                              |
| ------------------- | ------- | ------------------------------------------------------------------ |
| `ConnectionFailed`  | 503\*   | No HTTP response at all — DNS, TLS, timeout, refused                |
| `MalformedResponse` | 502\*   | A reply arrived that was not the envelope                          |
| `Unauthorized`      | 401     | Credential missing, wrong or revoked                                |
| `Forbidden`         | 403     | Missing scope, or agency not configured for API access              |
| `NotFound`          | 404     | No such record — or it belongs to another agency                    |
| `Conflict`          | 409     | Business refusal: dates taken, price moved, nothing due             |
| `ValidationFailed`  | 422     | Field-level rejection; read `errors()`                              |
| `RateLimited`       | 429     | Over budget; read `retryAfter()`                                    |
| `ServerError`       | 5xx     | Anything else                                                       |

\* synthesised — there was no real status to report.

## 7. Money is integer minor units

`330000` is $3,300.00, and `1000` is ¥1,000 — zero-decimal currencies exist. No
SDK converts, rounds or formats money. Presentation is the caller's job.

## 8. Retries are opt-in and idempotent-only

Default is zero retries: a public website would rather render "temporarily
unavailable" than hold a request open. A `RetryPolicy` may honour `Retry-After`
on a 429, and only for idempotent requests. Budgets are 120/min reads and 20/min
writes, per agency.

## 9. One transport seam, one fake

Each SDK defines a single-method `Transport` interface and ships a
`FakeTransport` alongside it. Host applications supply their own implementation
to route calls through their HTTP client — which is how retries, logging and
test interception stay the application's business instead of being reinvented in
the SDK.

## 10. No caching in the core

The core is stateless. Caching (agency branding is the obvious one — every page
needs it, it changes rarely) belongs in the framework bridge or the application,
where the cache and its invalidation already live.

## 11. The spec is the contract

`spec/api.json` is exported from the API itself (`artisan scramble:export`,
generated from its routes and controllers) and committed here. Every SDK tests
against it — see `tests/Contract/SpecCoverageTest.php` — in both directions:
nothing called has vanished, nothing published is unwrapped.

`spec/fixtures/*.json` are the shared response fixtures. Every SDK's tests use
these same files, so one contract change breaks all of them at once, which is
the point.

Clients are **hand-written, not generated**. The surface is fourteen endpoints;
generated code would be larger than this, and it would impose exception-throwing
semantics that contradict §6.

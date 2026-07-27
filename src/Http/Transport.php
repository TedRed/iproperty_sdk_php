<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Http;

use IPropertyPro\Sdk\Errors\ConnectionFailed;

/**
 * The one seam between this SDK and however your app makes HTTP calls.
 *
 * Deliberately a single method taking a value object and returning a value
 * object: it maps onto a `fetch`-shaped function in any other language, which
 * is what keeps sibling SDKs (TS next) structurally identical rather than
 * merely similar. Implement it to run calls through your framework's client —
 * the Laravel bridge does exactly this so `Http::fake()` still intercepts.
 */
interface Transport
{
    /**
     * @throws ConnectionFailed when the request never got an HTTP response
     *                          (DNS, TLS, timeout, refused connection).
     */
    public function send(ApiRequest $request): RawResponse;
}

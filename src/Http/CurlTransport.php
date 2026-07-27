<?php

declare(strict_types=1);

namespace IPropertyPro\Sdk\Http;

use IPropertyPro\Sdk\Errors\ConnectionFailed;

/**
 * The dependency-free default, so the SDK works in a plain PHP script.
 *
 * Applications with their own HTTP client should supply a Transport that wraps
 * it instead — that is how retries, logging and test fakes stay the host app's
 * business rather than being reinvented here.
 */
final class CurlTransport implements Transport
{
    public function send(ApiRequest $request): RawResponse
    {
        $handle = curl_init();

        $headers = [];
        foreach ($request->headers as $name => $value) {
            $headers[] = $name.': '.$value;
        }

        $options = [
            CURLOPT_URL => $request->fullUrl(),
            CURLOPT_CUSTOMREQUEST => $request->method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $request->timeout,
            CURLOPT_CONNECTTIMEOUT => $request->timeout,
            CURLOPT_SSL_VERIFYPEER => $request->verifyTls,
            CURLOPT_SSL_VERIFYHOST => $request->verifyTls ? 2 : 0,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => false,
        ];

        if ($request->body !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($request->body, JSON_THROW_ON_ERROR);
        }

        $responseHeaders = [];
        $options[CURLOPT_HEADERFUNCTION] = function ($_, string $line) use (&$responseHeaders): int {
            $length = strlen($line);
            $parts = explode(':', $line, 2);

            if (count($parts) === 2) {
                $responseHeaders[trim($parts[0])] = trim($parts[1]);
            }

            return $length;
        };

        curl_setopt_array($handle, $options);

        $body = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        // No status at all means we never got a reply — that is a connection
        // failure, distinct from the API answering with a 5xx.
        if ($body === false || $status === 0) {
            throw new ConnectionFailed($error !== '' ? $error : 'The request could not be completed');
        }

        return new RawResponse($status, (string) $body, $responseHeaders);
    }
}

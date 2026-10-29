<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Http;

use GoSuccess\EndOfLife\Exception\TransportException;

/**
 * Minimal HTTP transport abstraction.
 *
 * The library ships with {@see CurlHttpClient} (no Composer dependencies). Provide
 * your own implementation to route requests through an existing HTTP stack, e.g.
 * Laravel's Http facade or Guzzle, without the library depending on it.
 */
interface HttpClient
{
    /**
     * Send a request and return the response.
     *
     * Implementations MUST:
     * - throw a {@see TransportException} for transport-level failures (connection
     *   errors, timeouts, …), but never for HTTP error status codes, which are
     *   returned as a normal {@see Response};
     * - return the response headers with lower-cased names;
     * - not follow redirects: the client follows the `301` of a renamed product
     *   itself, and only on the API's own host.
     *
     * @throws TransportException
     */
    public function send(Request $request): Response;
}

<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Internal;

use Closure;
use GoSuccess\EndOfLife\ClientOptions;
use GoSuccess\EndOfLife\Exception\ApiException;
use GoSuccess\EndOfLife\Exception\NotFoundException;
use GoSuccess\EndOfLife\Exception\SerializationException;
use GoSuccess\EndOfLife\Exception\TransportException;
use GoSuccess\EndOfLife\Http\HttpClient;
use GoSuccess\EndOfLife\Http\Method;
use GoSuccess\EndOfLife\Http\Request;
use GoSuccess\EndOfLife\Http\Response;
use InvalidArgumentException;
use JsonException;

/**
 * Sends the requests of all resources: adds the shared headers and timeouts,
 * follows the redirects of renamed resources, retries transient failures,
 * turns every other status outside 2xx into an exception and decodes the JSON
 * object of the answer.
 *
 * @internal
 */
final class Connection
{
    /** Statuses whose `Location` the client follows. */
    private const array REDIRECTS = [301, 302, 303, 307, 308];

    /** @var non-empty-string */
    private readonly string $baseUri;

    /** Scheme, host and port of the base URI; a redirect must stay there. */
    private readonly string $origin;

    public function __construct(
        string $baseUri,
        private readonly ClientOptions $options,
        private readonly HttpClient $httpClient,
    ) {
        $baseUri = rtrim($baseUri, '/');
        $origin = self::origin($baseUri);

        if ($baseUri === '' || $origin === null) {
            throw new InvalidArgumentException('The base URI must be an absolute http or https URI.');
        }

        $this->baseUri = $baseUri;
        $this->origin = $origin;
    }

    /**
     * Read an endpoint and build the model of its answer.
     *
     * @template T
     *
     * @param string                            $path Below the base URI, e.g. `/products`.
     * @param Closure(array<string, mixed>): T $map  Builds the model; reads with {@see Json}.
     *
     * @return T
     *
     * @throws ApiException           for a status outside 2xx after the retries and redirects
     * @throws TransportException     for a network failure after the retries
     * @throws SerializationException if the answer does not match the specification
     */
    public function get(string $path, Closure $map): mixed
    {
        $request = $this->request($this->baseUri . $path);
        [$request, $response] = $this->send($request);

        if (!$response->isSuccessful) {
            throw ApiException::fromResponse($response, $request);
        }

        $endpoint = "{$request->method->value} {$path}";

        try {
            $data = json_decode($response->body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $data = null;
        }

        if (!\is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new SerializationException("{$endpoint} (HTTP {$response->statusCode}): the body is not a JSON object.");
        }

        try {
            /** @var array<string, mixed> $data */
            return $map($data);
        } catch (SerializationException $e) {
            throw new SerializationException("{$endpoint} (HTTP {$response->statusCode}): {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Like {@see get()}, but null when the API answers `404`, which it does for a
     * name it does not know.
     *
     * @template T
     *
     * @param Closure(array<string, mixed>): T $map
     *
     * @return T|null
     *
     * @throws ApiException
     * @throws TransportException
     * @throws SerializationException
     */
    public function find(string $path, Closure $map): mixed
    {
        try {
            return $this->get($path, $map);
        } catch (NotFoundException) {
            return null;
        }
    }

    /**
     * A path segment from a name, e.g. a product: percent-encoded, so a `/` or
     * `?` stays part of the name.
     *
     * @throws InvalidArgumentException for an empty name, `.` and `..`, which
     *                                  would address another endpoint
     */
    public static function segment(string $name, string $what): string
    {
        if ($name === '' || $name === '.' || $name === '..') {
            throw new InvalidArgumentException("The {$what} name must not be empty, \".\" or \"..\".");
        }

        return rawurlencode($name);
    }

    /**
     * Send a request, following redirects on the API's origin and retrying
     * network failures and 5xx. A `429` is returned at once.
     *
     * @return array{Request, Response} The request that got the final response, and that response.
     */
    private function send(Request $request): array
    {
        $redirects = 0;

        while (true) {
            $response = $this->attempt($request);

            if (!\in_array($response->statusCode, self::REDIRECTS, true) || $redirects >= $this->options->maxRedirects) {
                return [$request, $response];
            }

            $target = self::resolve($request->uri, $response->header('location'));

            if ($target === null || self::origin($target) !== $this->origin) {
                return [$request, $response];
            }

            $request = $this->request($target);
            $redirects++;
        }
    }

    private function attempt(Request $request): Response
    {
        for ($attempt = 0; ; $attempt++) {
            $retriesLeft = $attempt < $this->options->maxRetries;

            try {
                $response = $this->httpClient->send($request);
            } catch (TransportException $exception) {
                if (!$retriesLeft) {
                    throw $exception;
                }

                $this->wait($attempt);

                continue;
            }

            if ($response->statusCode < 500 || !$retriesLeft) {
                return $response;
            }

            $this->wait($attempt);
        }
    }

    /**
     * @param non-empty-string $uri
     */
    private function request(string $uri): Request
    {
        return new Request(
            method: Method::Get,
            uri: $uri,
            headers: [
                'User-Agent' => $this->options->userAgent,
                'Accept' => 'application/json',
            ],
            timeout: $this->options->timeout,
            connectTimeout: $this->options->connectTimeout,
        );
    }

    private function wait(int $attempt): void
    {
        $delay = min($this->options->maxRetryDelay, $this->options->retryBaseDelay * (2 ** $attempt));

        if ($delay > 0.0) {
            usleep((int) ($delay * 1_000_000));
        }
    }

    /**
     * `scheme://host[:port]` in lower case, or null if the URI is no absolute
     * http(s) URI.
     */
    private static function origin(string $uri): ?string
    {
        $parts = parse_url($uri);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (!\in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        return "{$scheme}://{$host}:{$port}";
    }

    /**
     * The target of a `Location` header: an absolute URI, or a reference relative
     * to the URI of the request (the API sends `/api/v1/products/ubuntu/`).
     *
     * @return non-empty-string|null
     */
    private static function resolve(string $base, string $location): ?string
    {
        $location = trim($location);

        if ($location === '' || preg_match('/[\x00-\x20\x7F]/', $location) === 1) {
            return null;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($base);
        $scheme = (string) ($parts['scheme'] ?? '');
        $authority = (string) ($parts['host'] ?? '') . (isset($parts['port']) ? ":{$parts['port']}" : '');

        if (str_starts_with($location, '//')) {
            return "{$scheme}:{$location}";
        }

        if (str_starts_with($location, '/')) {
            return "{$scheme}://{$authority}{$location}";
        }

        $path = (string) ($parts['path'] ?? '/');

        if (str_starts_with($location, '?') || str_starts_with($location, '#')) {
            // Only a query or fragment: the same path (RFC 3986, section 5.2.2).
            return "{$scheme}://{$authority}{$path}{$location}";
        }

        $directory = substr($path, 0, (int) strrpos($path, '/') + 1);

        return "{$scheme}://{$authority}{$directory}{$location}";
    }
}

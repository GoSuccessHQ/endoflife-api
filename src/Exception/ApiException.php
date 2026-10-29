<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Exception;

use GoSuccess\EndOfLife\Http\Request;
use GoSuccess\EndOfLife\Http\Response;
use RuntimeException;

/**
 * Base class for every answer outside 2xx: an HTTP error status, and a redirect
 * the client does not follow (too many, or to another host).
 *
 * Statuses without a class of their own (e.g. another 4xx) are thrown as this
 * class; the raw body stays available in {@see $responseBody}.
 */
class ApiException extends RuntimeException implements EndOfLifeException
{
    /**
     * @param int    $statusCode   HTTP status code of the response.
     * @param string $responseBody Raw response body; for a `404` the HTML error page.
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string $responseBody = '',
    ) {
        parent::__construct($message, $statusCode);
    }

    /**
     * Build the most specific exception type for an error response.
     *
     * @param Request    $request The request that got the response.
     * @param float|null $now     Current Unix time, to turn a `Retry-After` date into
     *                            {@see RateLimitException::$retryAfter}; defaults to the
     *                            system time.
     */
    public static function fromResponse(Response $response, Request $request, ?float $now = null): self
    {
        $status = $response->statusCode;
        $target = explode('?', $request->uri, 2)[0];
        $message = "{$request->method->value} {$target} failed with HTTP {$status}";

        if ($response->reasonPhrase !== '') {
            $message .= " {$response->reasonPhrase}";
        }

        $detail = ErrorDetails::message($response->body);
        $location = $response->header('location');

        if ($status >= 300 && $status < 400 && $location !== '') {
            $message .= " to {$location}";
        } elseif ($detail !== null) {
            $message .= ": {$detail}";
        }

        $arguments = [$message, $status, $response->body];

        return match (true) {
            $status === 404 => new NotFoundException(...$arguments),
            $status === 429 => new RateLimitException(
                ...$arguments,
                retryAfter: RateLimitException::parseRetryAfter($response->header('retry-after'), $now),
            ),
            $status >= 500 => new ServerException(...$arguments),
            default => new self(...$arguments),
        };
    }
}

<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife;

use InvalidArgumentException;

/**
 * Immutable transport, retry and redirect settings.
 */
final readonly class ClientOptions
{
    public const string DEFAULT_USER_AGENT = 'gosuccess/endoflife-api (+https://github.com/GoSuccessHQ/endoflife-api)';

    /**
     * The two timeouts travel with every request (see {@see Http\Request}), so they
     * also apply through a custom transport and take precedence over the defaults
     * the transport was created with.
     *
     * @param float  $timeout        Maximum duration of a request in seconds; `0.0` means
     *                               no limit.
     * @param float  $connectTimeout Maximum duration of the connection phase in seconds.
     * @param int    $maxRetries     How often a request is retried after a network failure
     *                               or a server error (5xx), e.g. a `502` or `503` of
     *                               Netlify, which hosts the API; `0` disables retries. A
     *                               `429` is never retried: it throws a
     *                               {@see Exception\RateLimitException} at once, and the
     *                               caller decides when to try again.
     * @param float  $retryBaseDelay Base delay in seconds of the exponential backoff.
     * @param float  $maxRetryDelay  Upper bound in seconds for a single wait.
     * @param int    $maxRedirects   How many redirects a request follows. The API answers
     *                               a renamed product, category or tag with `301`; `0`
     *                               turns every redirect into an {@see Exception\ApiException}.
     * @param string $userAgent      Value of the `User-Agent` header.
     */
    public function __construct(
        public float $timeout = 30.0,
        public float $connectTimeout = 10.0,
        public int $maxRetries = 2,
        public float $retryBaseDelay = 0.5,
        public float $maxRetryDelay = 5.0,
        public int $maxRedirects = 3,
        public string $userAgent = self::DEFAULT_USER_AGENT,
    ) {
        foreach (['timeout' => $timeout, 'connectTimeout' => $connectTimeout, 'retryBaseDelay' => $retryBaseDelay, 'maxRetryDelay' => $maxRetryDelay] as $name => $value) {
            // NAN and INF would turn into warnings or endless waits once converted for cURL or sleep().
            if (!is_finite($value) || $value < 0.0) {
                throw new InvalidArgumentException("{$name} must be a finite number that is not negative.");
            }
        }

        if ($maxRetries < 0) {
            throw new InvalidArgumentException('maxRetries must not be negative.');
        }

        if ($maxRedirects < 0) {
            throw new InvalidArgumentException('maxRedirects must not be negative.');
        }

        if (trim($userAgent) === '') {
            throw new InvalidArgumentException('userAgent must not be empty.');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $userAgent) === 1) {
            throw new InvalidArgumentException('userAgent must not contain control characters.');
        }
    }
}

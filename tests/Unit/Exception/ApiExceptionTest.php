<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Tests\Unit\Exception;

use GoSuccess\EndOfLife\Exception\ApiException;
use GoSuccess\EndOfLife\Exception\EndOfLifeException;
use GoSuccess\EndOfLife\Exception\ErrorDetails;
use GoSuccess\EndOfLife\Exception\NotFoundException;
use GoSuccess\EndOfLife\Exception\RateLimitException;
use GoSuccess\EndOfLife\Exception\ServerException;
use GoSuccess\EndOfLife\Http\Method;
use GoSuccess\EndOfLife\Http\Request;
use GoSuccess\EndOfLife\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiException::class)]
#[CoversClass(ErrorDetails::class)]
#[CoversClass(RateLimitException::class)]
final class ApiExceptionTest extends TestCase
{
    #[DataProvider('errorBodies')]
    public function testReadsAShortMessageFromTheBody(string $body, string $message): void
    {
        $exception = ApiException::fromResponse(new Response(400, $body), $this->request());

        self::assertSame($message, $exception->getMessage());
        self::assertSame($body, $exception->responseBody);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function errorBodies(): iterable
    {
        $prefix = 'GET https://endoflife.date/api/v1/products/php failed with HTTP 400';

        yield 'html error page' => ["<!DOCTYPE html>\n<html lang=\"en\"><title>Page not found</title></html>", $prefix];
        yield 'plain text' => ['Bad Gateway', "{$prefix}: Bad Gateway"];
        yield 'plain text over lines' => ["Too many\n  requests", "{$prefix}: Too many requests"];
        yield 'json message' => ['{"message":"Rate limited"}', "{$prefix}: Rate limited"];
        yield 'json error' => ['{"error":"Not found"}', "{$prefix}: Not found"];
        yield 'json without text' => ['{"error":1}', $prefix];
        yield 'json list' => ['["x"]', $prefix];
        yield 'empty' => ['', $prefix];
    }

    /**
     * @return iterable<string, array{int, class-string<ApiException>}>
     */
    public static function statuses(): iterable
    {
        yield '404' => [404, NotFoundException::class];
        yield '429' => [429, RateLimitException::class];
        yield '500' => [500, ServerException::class];
        yield '502' => [502, ServerException::class];
        yield '503' => [503, ServerException::class];
        yield '301' => [301, ApiException::class];
        yield '304' => [304, ApiException::class];
        yield '400' => [400, ApiException::class];
        yield '403' => [403, ApiException::class];
    }

    /**
     * @param class-string<ApiException> $class
     */
    #[DataProvider('statuses')]
    public function testMapsTheStatusToItsClass(int $status, string $class): void
    {
        $exception = ApiException::fromResponse(new Response($status), $this->request());

        self::assertSame($class, $exception::class);
        self::assertInstanceOf(EndOfLifeException::class, $exception);
        self::assertSame($status, $exception->statusCode);
        self::assertSame($status, $exception->getCode());
    }

    public function testIncludesTheReasonPhraseButNotTheQuery(): void
    {
        $exception = ApiException::fromResponse(
            new Response(404, '', [], 'Not Found'),
            new Request(Method::Get, 'https://endoflife.date/api/v1/products/php?secret=1'),
        );

        self::assertSame('GET https://endoflife.date/api/v1/products/php failed with HTTP 404 Not Found', $exception->getMessage());
    }

    public function testARedirectNamesItsTarget(): void
    {
        $exception = ApiException::fromResponse(new Response(301, 'Redirecting', ['location' => 'https://example.com/']), $this->request());

        self::assertSame('GET https://endoflife.date/api/v1/products/php failed with HTTP 301 to https://example.com/', $exception->getMessage());
    }

    public function testShortensHugeBodiesWithoutBreakingUtf8(): void
    {
        // One ASCII byte first, so that the cut after 300 bytes splits an "ä".
        $body = 'a' . str_repeat('ä', 400);
        $exception = ApiException::fromResponse(new Response(502, $body), $this->request());

        self::assertTrue(mb_check_encoding($exception->getMessage(), 'UTF-8'));
        self::assertStringEndsWith('…', $exception->getMessage());
        // The prefix (method, URL, status) plus at most 300 bytes of the body and the ellipsis.
        self::assertLessThan(400, \strlen($exception->getMessage()));
        self::assertSame($body, $exception->responseBody);
    }

    public function testParsesRetryAfter(): void
    {
        $now = 1_800_000_000.0;

        self::assertSame(30, RateLimitException::parseRetryAfter('30', $now));
        self::assertSame(0, RateLimitException::parseRetryAfter('Wed, 21 Oct 2015 07:28:00 GMT', $now));
        self::assertSame(120, RateLimitException::parseRetryAfter(gmdate('D, d M Y H:i:s', 1_800_000_120) . ' GMT', $now));
        // The obsolete RFC 850 and asctime() formats of RFC 9110.
        self::assertSame(120, RateLimitException::parseRetryAfter(gmdate('l, d-M-y H:i:s', 1_800_000_120) . ' GMT', $now));
        self::assertSame(3600, RateLimitException::parseRetryAfter('Fri Jan 15 09:00:00 2027', (float) gmmktime(8, 0, 0, 1, 15, 2027)));
        self::assertSame(3600, RateLimitException::parseRetryAfter('Wed Jan  6 09:00:00 2027', (float) gmmktime(8, 0, 0, 1, 6, 2027)));

        $future = gmdate('D, d M Y H:i:s', time() + 120) . ' GMT';
        self::assertGreaterThanOrEqual(118, RateLimitException::parseRetryAfter($future));
    }

    #[DataProvider('invalidRetryAfterValues')]
    public function testRejectsRetryAfterValuesOfNeitherForm(string $value): void
    {
        self::assertNull(RateLimitException::parseRetryAfter($value, 1_800_000_000.0));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRetryAfterValues(): iterable
    {
        yield 'empty' => [''];
        yield 'word' => ['soon'];
        // strtotime() read these as times of day, relative dates or zone offsets.
        yield 'fraction' => ['1.5'];
        yield 'fraction read as a time of day' => ['10.0'];
        yield 'negative' => ['-1'];
        yield 'signed' => ['+5'];
        yield 'military zone' => ['x'];
        yield 'now' => ['now'];
        yield 'relative' => ['tomorrow'];
        yield 'weekday' => ['Sun'];
        yield 'unit' => ['30s'];
        yield 'weekday that does not match' => ['Mon, 21 Oct 2015 07:28:00 GMT'];
        yield 'day beyond the month' => ['Thu, 32 Oct 2015 07:28:00 GMT'];
        yield 'other zone' => ['Wed, 21 Oct 2015 07:28:00 CET'];
        yield 'lower case' => ['wed, 21 oct 2015 07:28:00 GMT'];
        yield 'ISO 8601' => ['2015-10-21T07:28:00Z'];
    }

    public function testIgnoresAnInvalidRetryAfter(): void
    {
        $exception = ApiException::fromResponse(new Response(429, '', ['retry-after' => '1.5']), $this->request(), 1_800_000_000.0);

        self::assertInstanceOf(RateLimitException::class, $exception);
        self::assertNull($exception->retryAfter);
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('rateLimitHeaders')]
    public function testRateLimitExceptionCarriesTheAnnouncedWait(array $headers, ?int $retryAfter): void
    {
        $exception = ApiException::fromResponse(new Response(429, '', $headers), $this->request(), 1_800_000_000.0);

        self::assertInstanceOf(RateLimitException::class, $exception);
        self::assertSame($retryAfter, $exception->retryAfter);
    }

    /**
     * @return iterable<string, array{array<string, string>, int|null}>
     */
    public static function rateLimitHeaders(): iterable
    {
        yield 'seconds' => [['retry-after' => '7'], 7];
        yield 'date' => [['retry-after' => 'Fri, 15 Jan 2027 08:00:12 GMT'], 12];
        yield 'date in the past' => [['retry-after' => 'Wed, 21 Oct 2015 07:28:00 GMT'], 0];
        yield 'nothing' => [[], null];
    }

    private function request(): Request
    {
        return new Request(Method::Get, 'https://endoflife.date/api/v1/products/php');
    }
}

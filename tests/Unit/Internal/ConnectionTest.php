<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Tests\Unit\Internal;

use GoSuccess\EndOfLife\ClientOptions;
use GoSuccess\EndOfLife\Exception\ApiException;
use GoSuccess\EndOfLife\Exception\NotFoundException;
use GoSuccess\EndOfLife\Exception\RateLimitException;
use GoSuccess\EndOfLife\Exception\SerializationException;
use GoSuccess\EndOfLife\Exception\ServerException;
use GoSuccess\EndOfLife\Exception\TransportException;
use GoSuccess\EndOfLife\Http\Method;
use GoSuccess\EndOfLife\Http\Response;
use GoSuccess\EndOfLife\Internal\Connection;
use GoSuccess\EndOfLife\Internal\Json;
use GoSuccess\EndOfLife\Tests\Support\MockHttpClient;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Connection::class)]
final class ConnectionTest extends TestCase
{
    private const string BASE = 'https://endoflife.date/api/v1';

    private const string HTML_404 = "<!DOCTYPE html>\n<html lang=\"en\"><head><title>Page not found</title></head></html>";

    public function testEveryRequestCarriesTimeoutsAndHeaders(): void
    {
        $http = new MockHttpClient(new Response(200, '{}'));

        self::connection($http, new ClientOptions(timeout: 5.0, connectTimeout: 2.0))->get('/products', self::raw(...));

        $request = $http->requests[0];
        self::assertSame(Method::Get, $request->method);
        self::assertSame(self::BASE . '/products', $request->uri);
        self::assertSame(5.0, $request->timeout);
        self::assertSame(2.0, $request->connectTimeout);
        self::assertSame(ClientOptions::DEFAULT_USER_AGENT, $request->headers['User-Agent']);
        self::assertSame('application/json', $request->headers['Accept']);
    }

    public function testTheModelGetsTheDecodedObject(): void
    {
        $http = new MockHttpClient(new Response(200, '{"schema_version":"1.2.1","total":3}'));

        $data = self::connection($http)->get('/', self::raw(...));

        self::assertSame(['schema_version' => '1.2.1', 'total' => 3], $data);
    }

    public function testTrailingSlashOfTheBaseUriIsIgnored(): void
    {
        $http = new MockHttpClient(new Response(200, '{}'));

        new Connection(self::BASE . '/', new ClientOptions(), $http)->get('/tags', self::raw(...));

        self::assertSame(self::BASE . '/tags', $http->requests[0]->uri);
    }

    public function testNotFoundThrowsWithoutTheHtmlPage(): void
    {
        $http = new MockHttpClient(new Response(404, self::HTML_404, ['content-type' => 'text/html; charset=utf-8']));

        try {
            self::connection($http)->get('/products', self::raw(...));
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            self::assertSame('GET ' . self::BASE . '/products failed with HTTP 404', $e->getMessage());
            self::assertSame(self::HTML_404, $e->responseBody);
        }
    }

    public function testFindReturnsNullForNotFound(): void
    {
        $http = new MockHttpClient(new Response(404, self::HTML_404));

        self::assertNull(self::connection($http)->find('/products/nope', self::raw(...)));
    }

    public function testFindThrowsForOtherErrors(): void
    {
        $http = new MockHttpClient(new Response(403, 'Forbidden'));

        try {
            self::connection($http)->find('/products/php', self::raw(...));
            self::fail('Expected an ApiException.');
        } catch (ApiException $e) {
            self::assertSame(ApiException::class, $e::class);
            self::assertSame(403, $e->statusCode);
            self::assertStringEndsWith('failed with HTTP 403: Forbidden', $e->getMessage());
        }
    }

    public function testRateLimitIsThrownAtOnceAndNeverRetried(): void
    {
        $http = new MockHttpClient(new Response(429, '', ['retry-after' => '120']));
        $connection = self::connection($http, new ClientOptions(maxRetries: 3, retryBaseDelay: 0.0));

        try {
            $connection->get('/products/php', self::raw(...));
            self::fail('Expected a RateLimitException.');
        } catch (RateLimitException $e) {
            self::assertSame(120, $e->retryAfter);
        }

        self::assertSame(1, $http->callCount());
    }

    public function testServerErrorIsRetriedExactlyMaxRetriesTimes(): void
    {
        $http = new MockHttpClient(new Response(503), new Response(502), new Response(503));
        $connection = self::connection($http, new ClientOptions(maxRetries: 2, retryBaseDelay: 0.0));

        try {
            $connection->get('/products', self::raw(...));
            self::fail('Expected a ServerException.');
        } catch (ServerException $e) {
            self::assertSame(503, $e->statusCode);
        }

        self::assertSame(3, $http->callCount());
    }

    public function testServerErrorThenSuccess(): void
    {
        $http = new MockHttpClient(new Response(502), new Response(200, '{"a":1}'));

        $data = self::connection($http, new ClientOptions(maxRetries: 1, retryBaseDelay: 0.0))->get('/', self::raw(...));

        self::assertSame(['a' => 1], $data);
        self::assertSame(2, $http->callCount());
    }

    public function testTransportErrorIsRetriedAndFinallyRethrown(): void
    {
        $http = new MockHttpClient(new TransportException('connection reset'), new TransportException('timeout'));
        $connection = self::connection($http, new ClientOptions(maxRetries: 1, retryBaseDelay: 0.0));

        try {
            $connection->get('/', self::raw(...));
            self::fail('Expected a TransportException.');
        } catch (TransportException $e) {
            self::assertSame('timeout', $e->getMessage());
        }

        self::assertSame(2, $http->callCount());
    }

    public function testNoRetryWithoutRetries(): void
    {
        $http = new MockHttpClient(new Response(503));

        try {
            self::connection($http, new ClientOptions(maxRetries: 0))->get('/', self::raw(...));
            self::fail('Expected a ServerException.');
        } catch (ServerException) {
            self::assertSame(1, $http->callCount());
        }
    }

    public function testRetriesWaitWithExponentialBackoff(): void
    {
        $http = new MockHttpClient(new Response(503), new Response(503), new Response(200, '{}'));
        $connection = self::connection($http, new ClientOptions(maxRetries: 2, retryBaseDelay: 0.05, maxRetryDelay: 0.08));
        $start = microtime(true);

        $connection->get('/', self::raw(...));

        // 0.05 s, then 0.1 s capped at 0.08 s.
        self::assertGreaterThanOrEqual(0.13, microtime(true) - $start);
    }

    public function testRelativeRedirectOfARenamedProductIsFollowed(): void
    {
        $http = new MockHttpClient(
            new Response(301, '', ['location' => '/api/v1/products/ubuntu/']),
            new Response(200, '{"name":"ubuntu"}'),
        );

        $data = self::connection($http)->get('/products/ubuntu-linux', self::raw(...));

        self::assertSame(['name' => 'ubuntu'], $data);
        self::assertSame('https://endoflife.date/api/v1/products/ubuntu/', $http->requests[1]->uri);
        self::assertSame(ClientOptions::DEFAULT_USER_AGENT, $http->requests[1]->headers['User-Agent']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function redirectTargets(): iterable
    {
        yield 'absolute' => ['https://endoflife.date/api/v1/products/ubuntu', 'https://endoflife.date/api/v1/products/ubuntu'];
        yield 'absolute path' => ['/api/v1/products/ubuntu', 'https://endoflife.date/api/v1/products/ubuntu'];
        yield 'relative path' => ['ubuntu', 'https://endoflife.date/api/v1/products/ubuntu'];
        yield 'explicit default port' => ['https://endoflife.date:443/api/v1/products/ubuntu', 'https://endoflife.date:443/api/v1/products/ubuntu'];
    }

    #[DataProvider('redirectTargets')]
    public function testRedirectTargetsOnTheSameOrigin(string $location, string $expected): void
    {
        $http = new MockHttpClient(new Response(308, '', ['location' => $location]), new Response(200, '{}'));

        self::connection($http)->get('/products/ubuntu-linux', self::raw(...));

        self::assertSame($expected, $http->requests[1]->uri);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function foreignTargets(): iterable
    {
        yield 'other host' => ['https://example.com/api/v1/products/ubuntu'];
        yield 'protocol-relative other host' => ['//example.com/products/ubuntu'];
        yield 'plain http' => ['http://endoflife.date/api/v1/products/ubuntu'];
        yield 'other port' => ['https://endoflife.date:8443/api/v1/products/ubuntu'];
        yield 'other scheme' => ['file:///etc/passwd'];
    }

    #[DataProvider('foreignTargets')]
    public function testRedirectToAnotherOriginIsNotFollowed(string $location): void
    {
        $http = new MockHttpClient(new Response(301, '', ['location' => $location]));

        try {
            self::connection($http)->get('/products/ubuntu-linux', self::raw(...));
            self::fail('Expected an ApiException.');
        } catch (ApiException $e) {
            self::assertSame(301, $e->statusCode);
            self::assertStringEndsWith("failed with HTTP 301 to {$location}", $e->getMessage());
        }

        self::assertSame(1, $http->callCount());
    }

    public function testRedirectsStopAfterMaxRedirects(): void
    {
        $loop = new Response(301, '', ['location' => '/api/v1/products/a']);
        $http = new MockHttpClient($loop, $loop, $loop);

        try {
            self::connection($http, new ClientOptions(maxRedirects: 2))->get('/products/a', self::raw(...));
            self::fail('Expected an ApiException.');
        } catch (ApiException $e) {
            self::assertSame(301, $e->statusCode);
        }

        self::assertSame(3, $http->callCount());
    }

    public function testRedirectWithoutLocationThrows(): void
    {
        $http = new MockHttpClient(new Response(302));

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(302);

        self::connection($http)->get('/', self::raw(...));
    }

    public function testNotModifiedIsNoSuccess(): void
    {
        // The client sends no conditional headers, so a 304 has no body to use.
        $http = new MockHttpClient(new Response(304));

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(304);

        self::connection($http)->get('/', self::raw(...));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function bodiesThatAreNoObject(): iterable
    {
        yield 'empty' => [''];
        yield 'html' => ['<!DOCTYPE html><html></html>'];
        yield 'list' => ['[{"name":"php"}]'];
        yield 'string' => ['"php"'];
        yield 'broken' => ['{"name":'];
    }

    #[DataProvider('bodiesThatAreNoObject')]
    public function testBodyThatIsNoJsonObjectThrows(string $body): void
    {
        $http = new MockHttpClient(new Response(200, $body));

        try {
            self::connection($http)->get('/products', self::raw(...));
            self::fail('Expected a SerializationException.');
        } catch (SerializationException $e) {
            self::assertSame('GET /products (HTTP 200): the body is not a JSON object.', $e->getMessage());
        }
    }

    public function testModelErrorsNameTheEndpoint(): void
    {
        $http = new MockHttpClient(new Response(200, '{"total":"many"}'));

        try {
            self::connection($http)->get('/tags', static fn(array $data): int => Json::int($data, 'total', ''));
            self::fail('Expected a SerializationException.');
        } catch (SerializationException $e) {
            self::assertSame('GET /tags (HTTP 200): Field total must be an integer.', $e->getMessage());
            self::assertStringNotContainsString('many', $e->getMessage());
        }
    }

    public function testSegmentEncodesTheName(): void
    {
        self::assertSame('8.4', Connection::segment('8.4', 'release'));
        self::assertSame('a%2Fb%3Fc%20d', Connection::segment('a/b?c d', 'product'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function namesOfOtherEndpoints(): iterable
    {
        yield 'empty' => [''];
        yield 'dot' => ['.'];
        yield 'dots' => ['..'];
    }

    #[DataProvider('namesOfOtherEndpoints')]
    public function testSegmentRejectsNamesThatAddressAnotherEndpoint(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        Connection::segment($name, 'product');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidBaseUris(): iterable
    {
        yield 'empty' => [''];
        yield 'relative' => ['/api/v1'];
        yield 'ftp' => ['ftp://endoflife.date/api/v1'];
    }

    #[DataProvider('invalidBaseUris')]
    public function testBaseUriMustBeAbsoluteHttp(string $baseUri): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Connection($baseUri, new ClientOptions(), new MockHttpClient());
    }

    private static function connection(MockHttpClient $http, ClientOptions $options = new ClientOptions()): Connection
    {
        return new Connection(self::BASE, $options, $http);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function raw(array $data): array
    {
        return $data;
    }
}

<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Tests\Unit\Http;

use GoSuccess\EndOfLife\Exception\TransportException;
use GoSuccess\EndOfLife\Http\CurlHttpClient;
use GoSuccess\EndOfLife\Http\Method;
use GoSuccess\EndOfLife\Http\Request;
use GoSuccess\EndOfLife\Http\Response;
use GoSuccess\EndOfLife\Tests\Support\LocalServer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresOperatingSystemFamily;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real cURL transport against a local PHP web server.
 */
#[CoversClass(CurlHttpClient::class)]
final class CurlHttpClientTest extends TestCase
{
    private static ?LocalServer $server = null;

    public static function setUpBeforeClass(): void
    {
        self::$server = new LocalServer();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server?->stop();
        self::$server = null;
    }

    public function testGetReturnsStatusBodyAndLowerCasedHeaders(): void
    {
        $response = $this->send(new Request(Method::Get, $this->uri('/echo?a=1'), ['X-Test' => 'yes']));

        self::assertSame(200, $response->statusCode);
        self::assertTrue($response->isSuccessful);
        self::assertSame('one, two', $response->header('X-CUSTOM'));
        self::assertSame('OK', $response->reasonPhrase);

        $echo = $this->decode($response);
        self::assertSame('GET', $echo['method']);
        self::assertSame('a=1', $echo['query']);
        self::assertIsArray($echo['headers']);
        self::assertSame('yes', $echo['headers']['x-test']);
    }

    public function testRedirectsAreNotFollowed(): void
    {
        $response = $this->send(new Request(Method::Get, $this->uri('/redirect')));

        self::assertSame(301, $response->statusCode);
        self::assertSame('/echo', $response->header('location'));
    }

    public function testErrorResponsesAreReturned(): void
    {
        $response = $this->send(new Request(Method::Get, $this->uri('/status/404')));

        self::assertSame(404, $response->statusCode);
        self::assertFalse($response->isSuccessful);
        self::assertSame('{"error":"Status 404"}', $response->body);
    }

    public function testOnlyHttpAndHttpsAreFetched(): void
    {
        $this->expectException(TransportException::class);

        new CurlHttpClient()->send(new Request(Method::Get, 'file:///etc/hostname'));
    }

    public function testReusesTheHandleAcrossRequests(): void
    {
        $client = new CurlHttpClient();

        $first = $client->send(new Request(Method::Get, $this->uri('/echo?first=1'), ['X-First' => 'yes']));
        $second = $client->send(new Request(Method::Get, $this->uri('/echo')));

        self::assertSame('first=1', $this->decode($first)['query']);
        // No option of the first request (URI, headers) leaks into the second.
        $echo = $this->decode($second);
        self::assertSame('', $echo['query']);
        self::assertIsArray($echo['headers']);
        self::assertArrayNotHasKey('x-first', $echo['headers']);
    }

    public function testConnectionFailureThrowsTransportException(): void
    {
        $port = LocalServer::closedPort();

        $this->expectException(TransportException::class);

        new CurlHttpClient(connectTimeout: 1.0)->send(new Request(Method::Get, "http://127.0.0.1:{$port}/"));
    }

    public function testRequestTimeoutOverridesTheDefault(): void
    {
        $this->expectException(TransportException::class);

        $this->send(new Request(Method::Get, $this->uri('/sleep/2000'), timeout: 0.3));
    }

    #[RequiresOperatingSystemFamily('Linux')]
    public function testRequestConnectTimeoutOverridesTheDefault(): void
    {
        // A listener whose accept queue is full: Linux drops further SYNs, so a
        // connection hangs in the handshake until the connect timeout.
        $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error, \STREAM_SERVER_BIND | \STREAM_SERVER_LISTEN, stream_context_create(['socket' => ['backlog' => 0]]));
        self::assertNotFalse($listener, 'Unable to listen on a local port.');
        $address = (string) stream_socket_get_name($listener, false);
        // A backlog of 0 still queues a connection; these fill the queue and must stay open.
        $queued = [];

        for ($i = 0; $i < 2; ++$i) {
            $queued[] = @stream_socket_client("tcp://{$address}", $errno, $error, 0.2, \STREAM_CLIENT_CONNECT | \STREAM_CLIENT_ASYNC_CONNECT);
        }

        usleep(100_000);
        $start = microtime(true);

        try {
            // Without the request's connect timeout, the connection would wait for the whole 5 seconds.
            new CurlHttpClient(connectTimeout: 30.0)->send(new Request(Method::Get, "http://{$address}/", timeout: 5.0, connectTimeout: 0.2));
            self::fail('Expected a TransportException.');
        } catch (TransportException $e) {
            // The wording differs between libcurl versions ("Connection timed out
            // after ..." vs. "Failed to connect ...: Timeout was reached"), the
            // error code does not.
            self::assertSame(\CURLE_OPERATION_TIMEDOUT, $e->getCode());
        }

        self::assertLessThan(2.0, microtime(true) - $start);
        self::assertCount(2, $queued);
    }

    public function testTransportErrorMessageOmitsTheQueryString(): void
    {
        $port = LocalServer::closedPort();

        try {
            new CurlHttpClient(connectTimeout: 1.0)->send(new Request(Method::Get, "http://127.0.0.1:{$port}/products?name=secret"));
            self::fail('Expected a TransportException.');
        } catch (TransportException $e) {
            self::assertStringNotContainsString('secret', $e->getMessage());
        }
    }

    /**
     * @return non-empty-string
     */
    private function uri(string $path): string
    {
        self::assertNotNull(self::$server);

        return self::$server->baseUri . $path;
    }

    private function send(Request $request): Response
    {
        return new CurlHttpClient()->send($request);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(Response $response): array
    {
        $decoded = json_decode($response->body, true);
        self::assertIsArray($decoded);

        return $decoded;
    }
}

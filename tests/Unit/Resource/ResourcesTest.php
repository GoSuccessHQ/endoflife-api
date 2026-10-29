<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Tests\Unit\Resource;

use Closure;
use GoSuccess\EndOfLife\EndOfLife;
use GoSuccess\EndOfLife\Exception\NotFoundException;
use GoSuccess\EndOfLife\Http\Response;
use GoSuccess\EndOfLife\Model\FullProductListResponse;
use GoSuccess\EndOfLife\Model\IdentifierListResponse;
use GoSuccess\EndOfLife\Model\ProductListResponse;
use GoSuccess\EndOfLife\Model\ProductReleaseResponse;
use GoSuccess\EndOfLife\Model\ProductResponse;
use GoSuccess\EndOfLife\Model\UriListResponse;
use GoSuccess\EndOfLife\Resource\Categories;
use GoSuccess\EndOfLife\Resource\Identifiers;
use GoSuccess\EndOfLife\Resource\Products;
use GoSuccess\EndOfLife\Resource\Tags;
use GoSuccess\EndOfLife\Tests\Support\MockHttpClient;
use GoSuccess\EndOfLife\Tests\Support\Spec;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every operation of the specification through its method: the path it
 * requests, the model it builds from the specification's example, and what a
 * `404` becomes.
 */
#[CoversClass(EndOfLife::class)]
#[CoversClass(Products::class)]
#[CoversClass(Categories::class)]
#[CoversClass(Tags::class)]
#[CoversClass(Identifiers::class)]
final class ResourcesTest extends TestCase
{
    private const string BASE = 'https://endoflife.date/api/v1';

    /**
     * operationId => [call, requested path, model, whether a 404 is null].
     *
     * @return iterable<string, array{string, Closure(EndOfLife): mixed, string, class-string, bool}>
     */
    public static function operations(): iterable
    {
        yield 'index' => ['index', static fn(EndOfLife $eol): mixed => $eol->index(), '/', UriListResponse::class, false];
        yield 'products' => ['products', static fn(EndOfLife $eol): mixed => $eol->products->list(), '/products', ProductListResponse::class, false];
        yield 'products_full' => ['products_full', static fn(EndOfLife $eol): mixed => $eol->products->full(), '/products/full', FullProductListResponse::class, false];
        yield 'product' => ['product', static fn(EndOfLife $eol): mixed => $eol->products->get('php'), '/products/php', ProductResponse::class, true];
        yield 'product_release' => ['product_release', static fn(EndOfLife $eol): mixed => $eol->products->release('php', '8.4'), '/products/php/releases/8.4', ProductReleaseResponse::class, true];
        yield 'product_release_latest' => ['product_release_latest', static fn(EndOfLife $eol): mixed => $eol->products->latestRelease('php'), '/products/php/releases/latest', ProductReleaseResponse::class, true];
        yield 'categories' => ['categories', static fn(EndOfLife $eol): mixed => $eol->categories->list(), '/categories', UriListResponse::class, false];
        yield 'products_category' => ['products_category', static fn(EndOfLife $eol): mixed => $eol->categories->products('os'), '/categories/os', ProductListResponse::class, true];
        yield 'tags' => ['tags', static fn(EndOfLife $eol): mixed => $eol->tags->list(), '/tags', UriListResponse::class, false];
        yield 'products_tag' => ['products_tag', static fn(EndOfLife $eol): mixed => $eol->tags->products('linux-distribution'), '/tags/linux-distribution', ProductListResponse::class, true];
        yield 'identifier_types' => ['identifier_types', static fn(EndOfLife $eol): mixed => $eol->identifiers->types(), '/identifiers', UriListResponse::class, false];
        yield 'identifier_by_type' => ['identifier_by_type', static fn(EndOfLife $eol): mixed => $eol->identifiers->list('purl'), '/identifiers/purl', IdentifierListResponse::class, true];
    }

    /**
     * @param Closure(EndOfLife): mixed $call
     * @param class-string              $model
     */
    #[DataProvider('operations')]
    public function testRequestsThePathAndBuildsTheModel(string $operationId, Closure $call, string $path, string $model, bool $nullOnNotFound): void
    {
        $example = Spec::example(Spec::responseSchemaName($operationId));
        $http = new MockHttpClient(new Response(200, (string) json_encode($example)));

        $result = $call(new EndOfLife(httpClient: $http));

        self::assertInstanceOf($model, $result);
        self::assertSame(1, $http->callCount());
        self::assertSame(self::BASE . $path, $http->requests[0]->uri);
        self::assertSame('GET', $http->requests[0]->method->value);
    }

    /**
     * @param Closure(EndOfLife): mixed $call
     * @param class-string              $model
     */
    #[DataProvider('operations')]
    public function testThePathIsTheOneOfTheSpecification(string $operationId, Closure $call, string $path, string $model, bool $nullOnNotFound): void
    {
        $template = Spec::operation($operationId)['path'];
        $pattern = '#^' . preg_replace('/\\\{[a-z_]+\\\}/', '[^/]+', preg_quote($template, '#')) . '$#';

        self::assertMatchesRegularExpression($pattern, $path);
    }

    /**
     * @param Closure(EndOfLife): mixed $call
     * @param class-string              $model
     */
    #[DataProvider('operations')]
    public function testNotFound(string $operationId, Closure $call, string $path, string $model, bool $nullOnNotFound): void
    {
        $eol = new EndOfLife(httpClient: new MockHttpClient(new Response(404, '<!DOCTYPE html><html></html>')));

        if (!$nullOnNotFound) {
            $this->expectException(NotFoundException::class);
        }

        self::assertNull($call($eol));
    }

    public function testRedirectOfAnAliasIsFollowed(): void
    {
        $http = new MockHttpClient(
            new Response(301, '', ['location' => '/api/v1/products/ubuntu/']),
            new Response(200, (string) file_get_contents(__DIR__ . '/../../Fixtures/ubuntu.json')),
        );

        $product = new EndOfLife(httpClient: $http)->products->get('ubuntu-linux');

        self::assertSame('ubuntu', $product?->result->name);
        self::assertSame(self::BASE . '/products/ubuntu-linux', $http->requests[0]->uri);
        self::assertSame(self::BASE . '/products/ubuntu/', $http->requests[1]->uri);
    }

    public function testNamesAreSentAsOnePathSegment(): void
    {
        $http = new MockHttpClient(new Response(404), new Response(404));
        $eol = new EndOfLife(httpClient: $http);

        $eol->products->release('a/b', '1.0 beta');
        $eol->identifiers->list('?x');

        self::assertSame(self::BASE . '/products/a%2Fb/releases/1.0%20beta', $http->requests[0]->uri);
        self::assertSame(self::BASE . '/identifiers/%3Fx', $http->requests[1]->uri);
    }

    /**
     * @return iterable<string, array{Closure(EndOfLife): mixed}>
     */
    public static function callsWithANameOfAnotherEndpoint(): iterable
    {
        yield 'empty product' => [static fn(EndOfLife $eol): mixed => $eol->products->get('')];
        yield 'product ..' => [static fn(EndOfLife $eol): mixed => $eol->products->latestRelease('..')];
        yield 'empty release' => [static fn(EndOfLife $eol): mixed => $eol->products->release('php', '')];
        yield 'release .' => [static fn(EndOfLife $eol): mixed => $eol->products->release('php', '.')];
        yield 'empty category' => [static fn(EndOfLife $eol): mixed => $eol->categories->products('')];
        yield 'tag ..' => [static fn(EndOfLife $eol): mixed => $eol->tags->products('..')];
        yield 'empty identifier type' => [static fn(EndOfLife $eol): mixed => $eol->identifiers->list('')];
    }

    /**
     * @param Closure(EndOfLife): mixed $call
     */
    #[DataProvider('callsWithANameOfAnotherEndpoint')]
    public function testANameOfAnotherEndpointIsRejectedWithoutARequest(Closure $call): void
    {
        $http = new MockHttpClient();

        try {
            $call(new EndOfLife(httpClient: $http));
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            self::assertSame(0, $http->callCount());
        }
    }

    public function testACustomBaseUri(): void
    {
        $http = new MockHttpClient(new Response(200, (string) json_encode(Spec::example('UriListResponse'))));

        new EndOfLife(httpClient: $http, baseUri: 'http://127.0.0.1:8080/api/v1/')->tags->list();

        self::assertSame('http://127.0.0.1:8080/api/v1/tags', $http->requests[0]->uri);
    }
}

# endoflife.date API for PHP

A modern, strongly-typed, dependency-free PHP client for the
[endoflife.date API v1](https://endoflife.date/docs/api/v1/): end-of-life dates
and support phases of more than 400 products, such as PHP, Ubuntu and MariaDB.

## Features

- **Complete**: all 12 operations and all schemas of the
  [OpenAPI description](https://endoflife.date/docs/api/v1/openapi.yml)
  (version 1.2.1), checked against a committed snapshot in both directions.
- **Fully typed**: `final readonly` models, dates as `DateTimeImmutable`,
  checked with PHPStan at the highest level.
- **As strict as the specification**: an answer whose required field is
  missing or has another type throws a `SerializationException` that names the
  field, instead of handing on half-read data. Fields the API adds later are
  ignored, as the specification asks.
- **"Not found" is a result**: the lookup of an unknown product, release cycle,
  category, tag or identifier type returns `null`.
- **Renamed products work**: the client follows the API's `301`, on its own
  host only.
- **A `429` is never waited for**: it throws a `RateLimitException` at once,
  with the announced `Retry-After`.
- **No dependencies**: only `ext-curl` and `ext-json`; bring your own transport
  if you like, e.g. Laravel's Http facade.

## Requirements

- PHP 8.4 or newer
- `ext-curl`, `ext-json`

## Installation

```bash
composer require gosuccess/endoflife-api
```

## Quick start

endoflife.date needs no API key.

```php
use GoSuccess\EndOfLife\EndOfLife;

$eol = new EndOfLife();

$php = $eol->products->get('php')?->result      // null if the product is unknown
    ?? throw new RuntimeException('endoflife.date does not know php.');

foreach ($php->releases as $cycle) {
    echo $cycle->name, ' released ', $cycle->releaseDate->format('Y-m-d'),
        ', active support until ', $cycle->eoasFrom?->format('Y-m-d') ?? '-',
        ', security support until ', $cycle->eolFrom?->format('Y-m-d') ?? '-',
        ', latest ', $cycle->latest?->name ?? '-', "\n";
}
```

What `eoasFrom`, `eolFrom` and `eoesFrom` mean depends on the product; the
product's `labels` name its phases. For PHP, `eoasFrom` ends the active support
and `eolFrom` the security support. See [docs/products.md](docs/products.md#support-phases).

## Operations

| Method | Request | Operation |
| --- | --- | --- |
| `$eol->index()` | `GET /` | `index` |
| `$eol->products->list()` | `GET /products` | `products` |
| `$eol->products->full()` | `GET /products/full` | `products_full` |
| `$eol->products->get($product)` | `GET /products/{product}` | `product` |
| `$eol->products->release($product, $release)` | `GET /products/{product}/releases/{release}` | `product_release` |
| `$eol->products->latestRelease($product)` | `GET /products/{product}/releases/latest` | `product_release_latest` |
| `$eol->categories->list()` | `GET /categories` | `categories` |
| `$eol->categories->products($category)` | `GET /categories/{category}` | `products_category` |
| `$eol->tags->list()` | `GET /tags` | `tags` |
| `$eol->tags->products($tag)` | `GET /tags/{tag}` | `products_tag` |
| `$eol->identifiers->types()` | `GET /identifiers` | `identifier_types` |
| `$eol->identifiers->list($type)` | `GET /identifiers/{identifier_type}` | `identifier_by_type` |

Each method returns the model of its answer, named after the schema
(`ProductResponse`, `ProductListResponse`, …) with `schemaVersion`,
`generatedAt` and `result`. The methods with a name return `null` when the
API answers `404`. The specification names no fixed values: categories, tags
and identifier types are open lists, so they stay strings.

## Error handling

Every exception implements `GoSuccess\EndOfLife\Exception\EndOfLifeException`.

| Exception | When |
| --- | --- |
| `NotFoundException` | `404` of an operation without a name, e.g. a wrong base URI |
| `RateLimitException` | `429`, at once and never retried; `retryAfter` holds the seconds of `Retry-After`, if sent |
| `ServerException` | `5xx` after the retries, e.g. a `502` or `503` of Netlify, which hosts the API |
| `ApiException` | any other status outside 2xx: another `4xx`, a `304`, a redirect to another host or beyond `maxRedirects` |
| `SerializationException` | a `2xx` whose body is no JSON object or does not match the specification; the message names the request and the field (`GET /products/php (HTTP 200): Field result.releases[3].eolFrom must be a date (YYYY-MM-DD).`), never the value |
| `TransportException` | network failure or timeout after the retries |

All but `TransportException` and `SerializationException` extend `ApiException`
with `statusCode` and the raw `responseBody`. The API's `404` is its HTML error
page; the message leaves it out.

An empty name, `.` and `..` throw an `InvalidArgumentException` without a
request, since they would address another endpoint.

## Configuration and custom transport

`ClientOptions` sets `timeout` (30 s), `connectTimeout` (10 s), `maxRetries`
(2: network failures and `5xx`, never a `429`), `retryBaseDelay` (0.5 s) and
`maxRetryDelay` (5 s) of the exponential backoff, `maxRedirects` (3) and
`userAgent`. The timeouts travel with every request, so they also apply
through a custom transport.

A custom transport implements `GoSuccess\EndOfLife\Http\HttpClient`. With
Laravel's Http facade:

```php
use GoSuccess\EndOfLife\ClientOptions;
use GoSuccess\EndOfLife\EndOfLife;
use GoSuccess\EndOfLife\Exception\TransportException;
use GoSuccess\EndOfLife\Http\HttpClient;
use GoSuccess\EndOfLife\Http\Request;
use GoSuccess\EndOfLife\Http\Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class LaravelTransport implements HttpClient
{
    public function send(Request $request): Response
    {
        try {
            $response = Http::withHeaders($request->headers)
                ->withoutRedirecting()
                ->timeout($request->timeout ?? 30)
                ->connectTimeout($request->connectTimeout ?? 10)
                ->send($request->method->value, $request->uri);
        } catch (ConnectionException $e) {
            throw new TransportException("Request to {$request->uri} failed: {$e->getMessage()}", 0, $e);
        }

        $headers = [];

        foreach ($response->headers() as $name => $values) {
            $headers[strtolower($name)] = implode(', ', $values);
        }

        return new Response($response->status(), $response->body(), $headers, $response->reason());
    }
}

$eol = new EndOfLife(new ClientOptions(maxRetries: 1), new LaravelTransport());
```

The contract: throw `TransportException` only for network failures (never for
HTTP error statuses, which are returned as a `Response`), return header names
in lower case, and never follow redirects; the client follows them itself.
Honor `$request->timeout` and `$request->connectTimeout` when they are set. The
API only reads, so a request never has a body.

## Not covered

- **Conditional requests**: the API sends an `ETag` and answers
  `If-None-Match` with `304` (measured 2026-10-29). The client sends no
  conditional headers, so it always gets the whole answer; a `304` would throw
  an `ApiException`.
- **The old API** (`https://endoflife.date/api/{product}.json`), which v1
  replaces.
- Business logic: which release cycles count as supported for you is up to
  you; `isMaintained`, `isEol` and the dates are what the API says.

## Documentation and examples

- [docs/](docs/README.md): [index](docs/index.md), [products](docs/products.md),
  [categories](docs/categories.md), [tags](docs/tags.md),
  [identifiers](docs/identifiers.md)
- [examples/bootstrap.php](examples/bootstrap.php) (shared setup),
  [examples/release-cycles.php](examples/release-cycles.php),
  [examples/catalog.php](examples/catalog.php)
- [endoflife.date API documentation](https://endoflife.date/docs/api/v1/) and
  its [changelog](https://github.com/endoflife-date/endoflife.date/blob/master/CHANGELOG_API.md)

The package is not affiliated with endoflife.date.

## Development

```bash
composer check                                # code style, static analysis, unit tests
ENDOFLIFE_LIVE=1 composer test:integration    # one request per operation
composer specs                                # refresh resources/specs/openapi.yml
```

The unit tests send no requests: they build answers from the examples of the
specification and use real answers from `tests/Fixtures/` (listed with source
and date in `tests/Fixtures/README.md`). `SpecTest` pins every operation and
every field against `resources/specs/openapi.yml`; the weekly spec-drift
workflow refreshes the snapshot and fails when it changed. The live tests read
the real API and are skipped unless `ENDOFLIFE_LIVE=1`.

## License

MIT

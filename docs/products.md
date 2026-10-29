# Products

`$eol->products` covers `/products`. A product is addressed by its name
(`php`, `ubuntu`, `mariadb`) or one of its `aliases`; the API answers an alias
or a renamed product with `301` and a relative `Location`
(`/products/ubuntu-linux` → `/api/v1/products/ubuntu/`, measured 2026-10-29),
which the client follows.

| Method | Request | Operation | Returns |
| --- | --- | --- | --- |
| `list()` | `GET /products` | `products` | `ProductListResponse` |
| `full()` | `GET /products/full` | `products_full` | `FullProductListResponse` |
| `get($product)` | `GET /products/{product}` | `product` | `?ProductResponse` |
| `release($product, $release)` | `GET /products/{product}/releases/{release}` | `product_release` | `?ProductReleaseResponse` |
| `latestRelease($product)` | `GET /products/{product}/releases/latest` | `product_release_latest` | `?ProductReleaseResponse` |

The lookups return `null` when the API answers `404`: it does not know the
product, or the release cycle in it. An empty name, `.` and `..` throw an
`InvalidArgumentException` without a request, since they would address
another endpoint.

## The release cycles of a product

```php
$php = $eol->products->get('php')?->result;  // ProductDetails, or null

foreach ($php->releases as $cycle) {          // in the order of the API: newest first for PHP
    $cycle->name;                             // "8.4"
    $cycle->releaseDate;                      // DateTimeImmutable 2024-11-21
    $cycle->eoasFrom;                         // end of active support, ?DateTimeImmutable
    $cycle->eolFrom;                          // end of life, ?DateTimeImmutable
    $cycle->latest?->name;                    // "8.4.27"
}

$php->release('8.4');                         // ?ProductRelease by name, without a request
$eol->products->release('php', '8.4')?->result;
$eol->products->latestRelease('php')?->result;
```

`ProductResponse` adds `lastModified`, when the product was last changed, to
`schemaVersion` and `generatedAt`.

## Support phases

A `ProductRelease` has up to four phases. Which ones a product has, and what
they are called, is in `ProductDetails::$labels`; a phase the product does not
have is `null` there and in its release cycles.

| Field | Meaning | `null` when |
| --- | --- | --- |
| `isEoas`, `eoasFrom` | end of active support | the product has no such phase (`labels->eoas` is `null`); `eoasFrom` also when the date is not known |
| `isEol`, `eolFrom` | end of life | `eolFrom` when the date is not known |
| `isEoes`, `eoesFrom` | end of extended support | the product has no such phase, or the release cycle is not eligible for it |
| `isDiscontinued`, `discontinuedFrom` | discontinuation, mainly hardware | not applicable |

What the phases mean differs by product (labels measured 2026-10-29):

| Product | `labels->eoas` | `labels->eol` | `labels->eoes` |
| --- | --- | --- | --- |
| `php` | Active Support | Security Support | – |
| `ubuntu` | Hardware & Maintenance | Maintenance & Security Support | Expanded Security Maintenance |
| `mariadb` | – | Community support | Enterprise support |

So for PHP, `eoasFrom` ends the active support and `eolFrom` the security
updates. MariaDB has no active support phase: `isEoas` and `eoasFrom` are `null`.
An Ubuntu release without LTS has `isEoes` `null`, since it is not eligible for
Expanded Security Maintenance.

The other fields: `codename` and `label`, `isLts` and `ltsFrom`, `isMaintained`
(any support left, also extended), `latest` (a `ProductVersion` with `name`,
`date` and `link`, or `null`) and `custom` (the product's own fields as
`array<string, ?string>`, or `null`).

All dates are `DateTimeImmutable` at midnight UTC; times (`generatedAt`,
`lastModified`) are converted to UTC.

## The product itself

`ProductSummary` (from `list()` and the category and tag lists) has `name`,
`label`, `aliases`, `category`, `tags` and `uri`. `ProductDetails` adds
`versionCommand`, `identifiers` (`list<Identifier>` with `id` and `type`),
`labels`, `links` (`icon`, `html`, `releasePolicy`) and `releases`.

## Everything at once

`full()` returns every product with all its release cycles: 487 products, about
3 MB (measured 2026-10-29). Prefer `get()` for the products you need.

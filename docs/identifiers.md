# Identifiers

`$eol->identifiers` covers `/identifiers`: the identifiers that link a product
to other catalogs. The types were `purl`, `cpe` and `repology` on 2026-10-29;
the API may add more.

| Method | Request | Operation | Returns |
| --- | --- | --- | --- |
| `types()` | `GET /identifiers` | `identifier_types` | `UriListResponse` |
| `list($type)` | `GET /identifiers/{identifier_type}` | `identifier_by_type` | `?IdentifierListResponse` |

```php
foreach ($eol->identifiers->list('purl')?->result ?? [] as $reference) {
    $reference->identifier;      // "pkg:deb/debian/php"
    $reference->product->name;   // "php"
    $reference->product->uri;    // "https://endoflife.date/api/v1/products/php"
}
```

`list()` returns `null` for an unknown type. The identifiers of a single product
are in `ProductDetails::$identifiers`, each with `id` and `type`.

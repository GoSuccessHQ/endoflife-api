# Index

```php
$index = $eol->index();     // GET /, operation `index`: UriListResponse
```

`UriListResponse` has `schemaVersion`, `generatedAt` (UTC), `total` and
`result`, a `list<Uri>` with `name` and `uri`. The index lists `products`,
`categories` and `tags` (measured 2026-10-29); the identifier types are not in
it, `$eol->identifiers->types()` lists them.

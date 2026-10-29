# Tags

`$eol->tags` covers `/tags`. A product has one or more tags, such as
`linux-distribution`, `php-runtime` or `canonical`; its category is always one
of them. There were 90 tags on 2026-10-29.

| Method | Request | Operation | Returns |
| --- | --- | --- | --- |
| `list()` | `GET /tags` | `tags` | `UriListResponse` |
| `products($tag)` | `GET /tags/{tag}` | `products_tag` | `?ProductListResponse` |

```php
$eol->tags->list()->result;                               // list<Uri>
$eol->tags->products('linux-distribution')?->result;      // list<ProductSummary>, or null for an unknown tag
```

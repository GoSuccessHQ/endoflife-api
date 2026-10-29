# Categories

`$eol->categories` covers `/categories`. Every product has one category, such
as `os`, `lang`, `database`, `server-app` or `framework`; there were nine on
2026-10-29, and the API may add more.

| Method | Request | Operation | Returns |
| --- | --- | --- | --- |
| `list()` | `GET /categories` | `categories` | `UriListResponse` |
| `products($category)` | `GET /categories/{category}` | `products_category` | `?ProductListResponse` |

```php
foreach ($eol->categories->list()->result as $category) {
    $category->name;     // "os"
    $category->uri;      // "https://endoflife.date/api/v1/categories/os"
}

$eol->categories->products('database')?->result;  // list<ProductSummary>, or null for an unknown category
```

The categories stay strings, since the specification lets new values appear.
See [products.md](products.md#the-product-itself) for `ProductSummary`.

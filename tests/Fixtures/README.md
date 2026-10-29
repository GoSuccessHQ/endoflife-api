# Fixtures

Real answers of the endoflife.date API v1 (schema version 1.2.1), saved
unchanged on 2026-10-29:

| File | Request |
| --- | --- |
| `php.json` | `GET https://endoflife.date/api/v1/products/php` |
| `ubuntu.json` | `GET https://endoflife.date/api/v1/products/ubuntu` |

The tests pin values of these answers. The other tests build their answers
from the examples of the specification (`tests/Support/Spec.php`).

<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Exception;

/**
 * The requested resource does not exist (HTTP 404).
 *
 * Only thrown where the specification knows no `404`; the lookups of a product,
 * release cycle, category, tag or identifier type return `null` instead.
 */
final class NotFoundException extends ApiException {}

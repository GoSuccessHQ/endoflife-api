<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Model;

use GoSuccess\EndOfLife\Internal\Json;

/**
 * A link to a resource (`Uri`), e.g. a category and the URI of its products.
 */
final readonly class Uri
{
    /**
     * @param string $name Name of the URI, e.g. `os`.
     * @param string $uri  The URI, e.g. `https://endoflife.date/api/v1/categories/os`.
     */
    public function __construct(
        public string $name,
        public string $uri,
    ) {}

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     */
    public static function fromJson(array $data, string $path): self
    {
        return new self(
            Json::string($data, 'name', $path),
            Json::string($data, 'uri', $path),
        );
    }
}

<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Model;

use GoSuccess\EndOfLife\Internal\Json;

/**
 * A product in a list (`ProductSummary`); {@see ProductDetails} has everything,
 * including the release cycles.
 */
final readonly class ProductSummary
{
    /**
     * @param string       $name     Name of the product, e.g. `ubuntu`; the name the API takes.
     * @param string       $label    Label, e.g. `Ubuntu`.
     * @param list<string> $aliases  Other names, e.g. `ubuntu-linux`; the API redirects them to `name`.
     * @param string       $category Category, e.g. `os`.
     * @param list<string> $tags     Tags; the category is one of them.
     * @param string       $uri      The product's details in the API.
     */
    public function __construct(
        public string $name,
        public string $label,
        public array $aliases,
        public string $category,
        public array $tags,
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
            Json::string($data, 'label', $path),
            Json::strings($data, 'aliases', $path),
            Json::string($data, 'category', $path),
            Json::nonEmptyStrings($data, 'tags', $path),
            Json::string($data, 'uri', $path),
        );
    }
}

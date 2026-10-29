<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Model;

use GoSuccess\EndOfLife\Internal\Json;

/**
 * Everything about a product (`ProductDetails`), including its release cycles.
 */
final readonly class ProductDetails
{
    /**
     * @param string               $name           Name of the product, e.g. `ubuntu`.
     * @param string               $label          Label, e.g. `Ubuntu`.
     * @param list<string>         $aliases        Other names; the API redirects them to `name`.
     * @param string               $category       Category, e.g. `os`.
     * @param list<string>         $tags           Tags; the category is one of them.
     * @param string|null          $versionCommand Command that shows the installed version, e.g.
     *                                             `lsb_release --release`; null when not known.
     * @param list<Identifier>     $identifiers    Known identifiers (purl, repology, cpe, …).
     * @param ProductLabels        $labels         Names of the support phases.
     * @param ProductLinks         $links          Links of the product.
     * @param list<ProductRelease> $releases       The release cycles in the order of the API
     *                                             (newest first for PHP and Ubuntu).
     */
    public function __construct(
        public string $name,
        public string $label,
        public array $aliases,
        public string $category,
        public array $tags,
        public ?string $versionCommand,
        public array $identifiers,
        public ProductLabels $labels,
        public ProductLinks $links,
        public array $releases,
    ) {}

    /**
     * The release cycle with the given name, e.g. `8.4`; null if there is none.
     */
    public function release(string $name): ?ProductRelease
    {
        foreach ($this->releases as $release) {
            if ($release->name === $name) {
                return $release;
            }
        }

        return null;
    }

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     */
    public static function fromJson(array $data, string $path): self
    {
        $identifiers = [];

        foreach (Json::list($data, 'identifiers', $path) as $index => $identifier) {
            $identifiers[] = Identifier::fromJson($identifier, Json::join(Json::join($path, 'identifiers'), $index));
        }

        $releases = [];

        foreach (Json::list($data, 'releases', $path) as $index => $release) {
            $releases[] = ProductRelease::fromJson($release, Json::join(Json::join($path, 'releases'), $index));
        }

        return new self(
            Json::string($data, 'name', $path),
            Json::string($data, 'label', $path),
            Json::strings($data, 'aliases', $path),
            Json::string($data, 'category', $path),
            Json::nonEmptyStrings($data, 'tags', $path),
            Json::nullableString($data, 'versionCommand', $path),
            $identifiers,
            ProductLabels::fromJson(Json::object($data, 'labels', $path), Json::join($path, 'labels')),
            ProductLinks::fromJson(Json::object($data, 'links', $path), Json::join($path, 'links')),
            $releases,
        );
    }
}

<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Model;

use GoSuccess\EndOfLife\Internal\Json;

/**
 * The links of a product (`ProductDetails.links`).
 */
final readonly class ProductLinks
{
    /**
     * @param string|null $icon          The product's icon on simpleicons.org; null when it has none.
     * @param string      $html          The product's page on endoflife.date.
     * @param string|null $releasePolicy The product's release policy; null when none is public.
     */
    public function __construct(
        public ?string $icon,
        public string $html,
        public ?string $releasePolicy,
    ) {}

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     */
    public static function fromJson(array $data, string $path): self
    {
        return new self(
            Json::nullableString($data, 'icon', $path),
            Json::string($data, 'html', $path),
            Json::nullableString($data, 'releasePolicy', $path),
        );
    }
}

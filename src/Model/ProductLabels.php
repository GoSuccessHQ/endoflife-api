<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Model;

use GoSuccess\EndOfLife\Internal\Json;

/**
 * The names a product gives its support phases (`ProductDetails.labels`). A
 * phase the product does not have is null.
 */
final readonly class ProductLabels
{
    /**
     * @param string|null $eoas         The phase before the end of active support, e.g.
     *                                  `Active Support`; null without such a phase.
     * @param string|null $discontinued The discontinuation, e.g. `Discontinued`; mainly for
     *                                  hardware, always null for software.
     * @param string      $eol          The phase before the end of life, e.g. `Security Support`.
     * @param string|null $eoes         The phase before the end of extended support, e.g.
     *                                  `Extended Security Maintenance`; null without such a phase.
     */
    public function __construct(
        public ?string $eoas,
        public ?string $discontinued,
        public string $eol,
        public ?string $eoes,
    ) {}

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     */
    public static function fromJson(array $data, string $path): self
    {
        return new self(
            Json::nullableString($data, 'eoas', $path),
            Json::nullableString($data, 'discontinued', $path),
            Json::string($data, 'eol', $path),
            Json::nullableString($data, 'eoes', $path),
        );
    }
}

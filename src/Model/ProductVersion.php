<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Model;

use DateTimeImmutable;
use GoSuccess\EndOfLife\Internal\Json;

/**
 * A version of a product (`ProductVersion`), e.g. the latest of a release cycle.
 */
final readonly class ProductVersion
{
    /**
     * @param string                 $name Name of the version, e.g. `22.04.2`.
     * @param DateTimeImmutable|null $date Release date at midnight UTC; null when not known.
     * @param string|null            $link Changelog or release notes; null when there is no
     *                                     public link or it is not known.
     */
    public function __construct(
        public string $name,
        public ?DateTimeImmutable $date,
        public ?string $link,
    ) {}

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     */
    public static function fromJson(array $data, string $path): self
    {
        Json::requireKeys($data, $path, 'date', 'link');

        return new self(
            Json::string($data, 'name', $path),
            Json::nullableDate($data, 'date', $path),
            Json::nullableString($data, 'link', $path),
        );
    }
}

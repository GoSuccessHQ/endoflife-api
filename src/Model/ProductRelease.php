<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Model;

use DateTimeImmutable;
use GoSuccess\EndOfLife\Internal\Json;

/**
 * A release cycle of a product (`ProductRelease`), e.g. PHP `8.4` or Ubuntu
 * `22.04`, with its support phases.
 *
 * The phases a product does not have are null: without an active support phase
 * (the product's {@see ProductLabels::$eoas} is null) `isEoas` and `eoasFrom` are
 * null, and likewise for the extended support phase and the discontinuation,
 * which is mainly used for hardware. Dates are at midnight UTC.
 */
final readonly class ProductRelease
{
    /**
     * @param string                          $name             Name of the release cycle, e.g. `22.04`.
     * @param string|null                     $codename         Codename, e.g. `Jammy Jellyfish`; null when
     *                                                          there is none or it is not known.
     * @param string                          $label            Label, e.g. `22.04 'Jammy Jellyfish' (LTS)`.
     * @param DateTimeImmutable               $releaseDate      Release date of the release cycle.
     * @param bool                            $isLts            Whether the release cycle receives long-term
     *                                                          support (LTS).
     * @param DateTimeImmutable|null          $ltsFrom          Start of the LTS phase; null when `isLts` is
     *                                                          false, when it starts with the release, or
     *                                                          when the date is not known.
     * @param bool|null                       $isEoas           Whether the active support phase is over; null
     *                                                          when the product has no such phase.
     * @param DateTimeImmutable|null          $eoasFrom         End of active support; null when the product
     *                                                          has no such phase or the date is not known.
     * @param bool                            $isEol            Whether the release cycle is end of life (EOL);
     *                                                          the phase that ends is named by the product's
     *                                                          {@see ProductLabels::$eol}, e.g. `Security Support`
     *                                                          for PHP.
     * @param DateTimeImmutable|null          $eolFrom          End of life; null when the date is not known.
     * @param bool|null                       $isDiscontinued   Whether the release cycle is discontinued; null
     *                                                          when not applicable.
     * @param DateTimeImmutable|null          $discontinuedFrom Discontinuation date; null when not applicable
     *                                                          or not known.
     * @param bool|null                       $isEoes           Whether the extended support phase is over; null
     *                                                          when the product has no such phase or the
     *                                                          release cycle is not eligible for it.
     * @param DateTimeImmutable|null          $eoesFrom         End of extended support; null when the product
     *                                                          has no such phase, the release cycle is not
     *                                                          eligible or the date is not known.
     * @param bool                            $isMaintained     Whether the release cycle still has some level of
     *                                                          support, even extended support.
     * @param ProductVersion|null             $latest           Latest version of the release cycle; null when
     *                                                          none is documented.
     * @param array<string, string|null>|null $custom           Custom fields of the product, e.g.
     *                                                          `['chromeVersion' => 'M136']`; null when the
     *                                                          product declares none.
     */
    public function __construct(
        public string $name,
        public ?string $codename,
        public string $label,
        public DateTimeImmutable $releaseDate,
        public bool $isLts,
        public ?DateTimeImmutable $ltsFrom,
        public ?bool $isEoas,
        public ?DateTimeImmutable $eoasFrom,
        public bool $isEol,
        public ?DateTimeImmutable $eolFrom,
        public ?bool $isDiscontinued,
        public ?DateTimeImmutable $discontinuedFrom,
        public ?bool $isEoes,
        public ?DateTimeImmutable $eoesFrom,
        public bool $isMaintained,
        public ?ProductVersion $latest,
        public ?array $custom,
    ) {}

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     */
    public static function fromJson(array $data, string $path): self
    {
        Json::requireKeys($data, $path, 'codename', 'ltsFrom', 'eolFrom', 'latest');
        $latest = Json::nullableObject($data, 'latest', $path);

        return new self(
            Json::string($data, 'name', $path),
            Json::nullableString($data, 'codename', $path),
            Json::string($data, 'label', $path),
            Json::date($data, 'releaseDate', $path),
            Json::bool($data, 'isLts', $path),
            Json::nullableDate($data, 'ltsFrom', $path),
            Json::optionalBool($data, 'isEoas', $path),
            Json::nullableDate($data, 'eoasFrom', $path),
            Json::bool($data, 'isEol', $path),
            Json::nullableDate($data, 'eolFrom', $path),
            Json::optionalBool($data, 'isDiscontinued', $path),
            Json::nullableDate($data, 'discontinuedFrom', $path),
            Json::nullableBool($data, 'isEoes', $path),
            Json::nullableDate($data, 'eoesFrom', $path),
            Json::bool($data, 'isMaintained', $path),
            $latest === null ? null : ProductVersion::fromJson($latest, Json::join($path, 'latest')),
            Json::nullableStringMap($data, 'custom', $path),
        );
    }
}

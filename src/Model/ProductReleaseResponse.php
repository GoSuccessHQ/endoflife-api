<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Model;

use DateTimeImmutable;
use GoSuccess\EndOfLife\Internal\Json;

/**
 * A release cycle of a product (`ProductReleaseResponse`).
 */
final readonly class ProductReleaseResponse
{
    /**
     * @param string            $schemaVersion Version of the schema, e.g. `1.2.1`.
     * @param DateTimeImmutable $generatedAt   When the answer was generated, in UTC.
     * @param ProductRelease    $result        The release cycle.
     */
    public function __construct(
        public string $schemaVersion,
        public DateTimeImmutable $generatedAt,
        public ProductRelease $result,
    ) {}

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     */
    public static function fromJson(array $data): self
    {
        return new self(
            Json::string($data, 'schema_version', ''),
            Json::time($data, 'generated_at', ''),
            ProductRelease::fromJson(Json::object($data, 'result', ''), 'result'),
        );
    }
}

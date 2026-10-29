<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Model;

use DateTimeImmutable;
use GoSuccess\EndOfLife\Internal\Json;

/**
 * A product with all its details and release cycles (`ProductResponse`).
 */
final readonly class ProductResponse
{
    /**
     * @param string            $schemaVersion Version of the schema, e.g. `1.2.1`.
     * @param DateTimeImmutable $generatedAt   When the answer was generated, in UTC.
     * @param DateTimeImmutable $lastModified  When the product was last modified, in UTC.
     * @param ProductDetails    $result        The product.
     */
    public function __construct(
        public string $schemaVersion,
        public DateTimeImmutable $generatedAt,
        public DateTimeImmutable $lastModified,
        public ProductDetails $result,
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
            Json::time($data, 'last_modified', ''),
            ProductDetails::fromJson(Json::object($data, 'result', ''), 'result'),
        );
    }
}

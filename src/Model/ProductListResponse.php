<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Model;

use DateTimeImmutable;
use GoSuccess\EndOfLife\Internal\Json;

/**
 * A list of products, each as a summary (`ProductListResponse`).
 */
final readonly class ProductListResponse
{
    /**
     * @param string               $schemaVersion Version of the schema, e.g. `1.2.1`.
     * @param DateTimeImmutable    $generatedAt   When the answer was generated, in UTC.
     * @param int                  $total         Number of products in the list.
     * @param list<ProductSummary> $result        The products.
     */
    public function __construct(
        public string $schemaVersion,
        public DateTimeImmutable $generatedAt,
        public int $total,
        public array $result,
    ) {}

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     */
    public static function fromJson(array $data): self
    {
        $result = [];

        foreach (Json::list($data, 'result', '') as $index => $item) {
            $result[] = ProductSummary::fromJson($item, "result[{$index}]");
        }

        return new self(
            Json::string($data, 'schema_version', ''),
            Json::time($data, 'generated_at', ''),
            Json::int($data, 'total', ''),
            $result,
        );
    }
}

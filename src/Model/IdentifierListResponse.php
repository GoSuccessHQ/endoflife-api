<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Model;

use DateTimeImmutable;
use GoSuccess\EndOfLife\Internal\Json;

/**
 * All identifiers of a type, each with its product (`IdentifierListResponse`).
 */
final readonly class IdentifierListResponse
{
    /**
     * @param string                    $schemaVersion Version of the schema, e.g. `1.2.1`.
     * @param DateTimeImmutable         $generatedAt   When the answer was generated, in UTC.
     * @param int                       $total         Number of identifiers in the list.
     * @param list<IdentifierReference> $result        The identifiers.
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
            $result[] = IdentifierReference::fromJson($item, "result[{$index}]");
        }

        return new self(
            Json::string($data, 'schema_version', ''),
            Json::time($data, 'generated_at', ''),
            Json::int($data, 'total', ''),
            $result,
        );
    }
}

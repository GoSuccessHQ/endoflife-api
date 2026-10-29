<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Model;

use GoSuccess\EndOfLife\Internal\Json;

/**
 * An identifier of a product (`Identifier`), such as a purl, repology or cpe
 * identifier.
 */
final readonly class Identifier
{
    /**
     * @param string $id   The identifier, e.g. `cpe:/o:canonical:ubuntu_linux`.
     * @param string $type Its type, e.g. `cpe`; see {@see \GoSuccess\EndOfLife\Resource\Identifiers::types()}.
     */
    public function __construct(
        public string $id,
        public string $type,
    ) {}

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     */
    public static function fromJson(array $data, string $path): self
    {
        return new self(
            Json::string($data, 'id', $path),
            Json::string($data, 'type', $path),
        );
    }
}

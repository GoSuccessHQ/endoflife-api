<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Model;

use GoSuccess\EndOfLife\Internal\Json;

/**
 * An identifier and the product it belongs to (an entry of
 * `IdentifierListResponse.result`).
 */
final readonly class IdentifierReference
{
    /**
     * @param string $identifier The identifier, e.g. `cpe:/o:canonical:ubuntu_linux`.
     * @param Uri    $product    The product: its name and the URI of its details.
     */
    public function __construct(
        public string $identifier,
        public Uri $product,
    ) {}

    /**
     * @internal
     *
     * @param array<string, mixed> $data
     */
    public static function fromJson(array $data, string $path): self
    {
        return new self(
            Json::string($data, 'identifier', $path),
            Uri::fromJson(Json::object($data, 'product', $path), Json::join($path, 'product')),
        );
    }
}

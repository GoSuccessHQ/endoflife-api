<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Resource;

use GoSuccess\EndOfLife\Exception\ApiException;
use GoSuccess\EndOfLife\Exception\SerializationException;
use GoSuccess\EndOfLife\Exception\TransportException;
use GoSuccess\EndOfLife\Internal\Connection;
use GoSuccess\EndOfLife\Model\IdentifierListResponse;
use GoSuccess\EndOfLife\Model\UriListResponse;
use InvalidArgumentException;

/**
 * The identifiers of the products (`/identifiers`), by type: `purl`, `cpe` and
 * `repology`. New types may appear.
 */
final class Identifiers
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection) {}

    /**
     * Every identifier type with the URI of its identifiers (`GET /identifiers`,
     * `identifier_types`).
     *
     * @throws ApiException
     * @throws TransportException
     * @throws SerializationException
     */
    public function types(): UriListResponse
    {
        return $this->connection->get('/identifiers', UriListResponse::fromJson(...));
    }

    /**
     * Every identifier of a type, each with its product
     * (`GET /identifiers/{identifier_type}`, `identifier_by_type`); null if the
     * API does not know the type.
     *
     * @param string $type The identifier type, e.g. `purl`.
     *
     * @throws InvalidArgumentException for an empty name, `.` or `..`
     * @throws ApiException
     * @throws TransportException
     * @throws SerializationException
     */
    public function list(string $type): ?IdentifierListResponse
    {
        return $this->connection->find('/identifiers/' . Connection::segment($type, 'identifier type'), IdentifierListResponse::fromJson(...));
    }
}

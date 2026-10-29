<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Resource;

use GoSuccess\EndOfLife\Exception\ApiException;
use GoSuccess\EndOfLife\Exception\SerializationException;
use GoSuccess\EndOfLife\Exception\TransportException;
use GoSuccess\EndOfLife\Internal\Connection;
use GoSuccess\EndOfLife\Model\ProductListResponse;
use GoSuccess\EndOfLife\Model\UriListResponse;
use InvalidArgumentException;

/**
 * The tags of the products (`/tags`), e.g. `linux-distribution` or `canonical`.
 * A product has one or more; its category is one of them.
 */
final class Tags
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection) {}

    /**
     * Every tag with the URI of its products (`GET /tags`, `tags`).
     *
     * @throws ApiException
     * @throws TransportException
     * @throws SerializationException
     */
    public function list(): UriListResponse
    {
        return $this->connection->get('/tags', UriListResponse::fromJson(...));
    }

    /**
     * The products with a tag as summaries (`GET /tags/{tag}`, `products_tag`);
     * null if the API does not know the tag.
     *
     * @param string $tag Name of the tag, e.g. `linux-distribution`.
     *
     * @throws InvalidArgumentException for an empty name, `.` or `..`
     * @throws ApiException
     * @throws TransportException
     * @throws SerializationException
     */
    public function products(string $tag): ?ProductListResponse
    {
        return $this->connection->find('/tags/' . Connection::segment($tag, 'tag'), ProductListResponse::fromJson(...));
    }
}

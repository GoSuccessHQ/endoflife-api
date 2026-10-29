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
 * The categories of the products (`/categories`), e.g. `os`, `lang` or `database`.
 * Every product has one; new categories may appear.
 */
final class Categories
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection) {}

    /**
     * Every category with the URI of its products (`GET /categories`, `categories`).
     *
     * @throws ApiException
     * @throws TransportException
     * @throws SerializationException
     */
    public function list(): UriListResponse
    {
        return $this->connection->get('/categories', UriListResponse::fromJson(...));
    }

    /**
     * The products of a category as summaries (`GET /categories/{category}`,
     * `products_category`); null if the API does not know the category.
     *
     * @param string $category Name of the category, e.g. `os`.
     *
     * @throws InvalidArgumentException for an empty name, `.` or `..`
     * @throws ApiException
     * @throws TransportException
     * @throws SerializationException
     */
    public function products(string $category): ?ProductListResponse
    {
        return $this->connection->find('/categories/' . Connection::segment($category, 'category'), ProductListResponse::fromJson(...));
    }
}

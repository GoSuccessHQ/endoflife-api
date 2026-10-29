<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Resource;

use GoSuccess\EndOfLife\Exception\ApiException;
use GoSuccess\EndOfLife\Exception\SerializationException;
use GoSuccess\EndOfLife\Exception\TransportException;
use GoSuccess\EndOfLife\Internal\Connection;
use GoSuccess\EndOfLife\Model\FullProductListResponse;
use GoSuccess\EndOfLife\Model\ProductListResponse;
use GoSuccess\EndOfLife\Model\ProductReleaseResponse;
use GoSuccess\EndOfLife\Model\ProductResponse;
use InvalidArgumentException;

/**
 * The products and their release cycles (`/products`).
 *
 * A product is addressed by its name or one of its aliases (`ubuntu-linux`);
 * the API redirects an alias, and a renamed product, to the current name.
 */
final class Products
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection) {}

    /**
     * Every product as a summary (`GET /products`, `products`).
     *
     * @throws ApiException
     * @throws TransportException
     * @throws SerializationException
     */
    public function list(): ProductListResponse
    {
        return $this->connection->get('/products', ProductListResponse::fromJson(...));
    }

    /**
     * Every product with all its details and release cycles (`GET /products/full`,
     * `products_full`): nearly all data of endoflife.date, about 3 MB. Prefer
     * {@see get()} for the products you need.
     *
     * @throws ApiException
     * @throws TransportException
     * @throws SerializationException
     */
    public function full(): FullProductListResponse
    {
        return $this->connection->get('/products/full', FullProductListResponse::fromJson(...));
    }

    /**
     * A product with all its details and release cycles (`GET /products/{product}`,
     * `product`); null if the API does not know it.
     *
     * @param string $product Name or alias of the product, e.g. `php`.
     *
     * @throws InvalidArgumentException for an empty name, `.` or `..`
     * @throws ApiException
     * @throws TransportException
     * @throws SerializationException
     */
    public function get(string $product): ?ProductResponse
    {
        return $this->connection->find('/products/' . Connection::segment($product, 'product'), ProductResponse::fromJson(...));
    }

    /**
     * A release cycle of a product (`GET /products/{product}/releases/{release}`,
     * `product_release`); null if the API knows neither the product nor the
     * release cycle in it.
     *
     * @param string $product Name or alias of the product, e.g. `php`.
     * @param string $release Name of the release cycle, e.g. `8.4`.
     *
     * @throws InvalidArgumentException for an empty name, `.` or `..`
     * @throws ApiException
     * @throws TransportException
     * @throws SerializationException
     */
    public function release(string $product, string $release): ?ProductReleaseResponse
    {
        return $this->connection->find(
            '/products/' . Connection::segment($product, 'product') . '/releases/' . Connection::segment($release, 'release'),
            ProductReleaseResponse::fromJson(...),
        );
    }

    /**
     * The latest release cycle of a product (`GET /products/{product}/releases/latest`,
     * `product_release_latest`); null if the API does not know the product.
     *
     * @param string $product Name or alias of the product, e.g. `php`.
     *
     * @throws InvalidArgumentException for an empty name, `.` or `..`
     * @throws ApiException
     * @throws TransportException
     * @throws SerializationException
     */
    public function latestRelease(string $product): ?ProductReleaseResponse
    {
        return $this->connection->find('/products/' . Connection::segment($product, 'product') . '/releases/latest', ProductReleaseResponse::fromJson(...));
    }
}

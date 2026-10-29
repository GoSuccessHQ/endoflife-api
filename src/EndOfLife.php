<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife;

use GoSuccess\EndOfLife\Exception\ApiException;
use GoSuccess\EndOfLife\Exception\SerializationException;
use GoSuccess\EndOfLife\Exception\TransportException;
use GoSuccess\EndOfLife\Http\CurlHttpClient;
use GoSuccess\EndOfLife\Http\HttpClient;
use GoSuccess\EndOfLife\Internal\Connection;
use GoSuccess\EndOfLife\Model\UriListResponse;
use GoSuccess\EndOfLife\Resource\Categories;
use GoSuccess\EndOfLife\Resource\Identifiers;
use GoSuccess\EndOfLife\Resource\Products;
use GoSuccess\EndOfLife\Resource\Tags;

/**
 * Client for the endoflife.date API v1 (`https://endoflife.date/api/v1`); entry
 * point of the package. The API is public and needs no key.
 *
 * Each area is a `public readonly` property; all share one connection
 * (options, transport, retries).
 */
final class EndOfLife
{
    public const string DEFAULT_BASE_URI = 'https://endoflife.date/api/v1';

    /** The products and their release cycles (`/products`). */
    public readonly Products $products;

    /** The categories of the products (`/categories`). */
    public readonly Categories $categories;

    /** The tags of the products (`/tags`). */
    public readonly Tags $tags;

    /** The identifiers of the products, by type (`/identifiers`). */
    public readonly Identifiers $identifiers;

    private readonly Connection $connection;

    /**
     * @param HttpClient|null $httpClient Transport; defaults to {@see CurlHttpClient}.
     * @param string          $baseUri    Base URI of the API.
     */
    public function __construct(
        ClientOptions $options = new ClientOptions(),
        ?HttpClient $httpClient = null,
        string $baseUri = self::DEFAULT_BASE_URI,
    ) {
        $this->connection = new Connection($baseUri, $options, $httpClient ?? new CurlHttpClient($options->timeout, $options->connectTimeout));

        $this->products = new Products($this->connection);
        $this->categories = new Categories($this->connection);
        $this->tags = new Tags($this->connection);
        $this->identifiers = new Identifiers($this->connection);
    }

    /**
     * The main endpoints of the API (`GET /`, `index`).
     *
     * @throws ApiException
     * @throws TransportException
     * @throws SerializationException
     */
    public function index(): UriListResponse
    {
        return $this->connection->get('/', UriListResponse::fromJson(...));
    }
}

<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Tests\Integration;

use GoSuccess\EndOfLife\Tests\Support\LiveTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Every operation once against the live API: each answer must pass the models,
 * which are as strict as the specification.
 */
#[CoversNothing]
final class LiveTest extends LiveTestCase
{
    public function testIndex(): void
    {
        $index = $this->client->index();

        self::assertContains('products', array_map(static fn($uri): string => $uri->name, $index->result));
        self::assertSame($index->total, \count($index->result));
    }

    public function testProducts(): void
    {
        $products = $this->client->products->list();

        self::assertGreaterThan(100, $products->total);
        self::assertContains('php', array_map(static fn($product): string => $product->name, $products->result));
    }

    public function testFullProducts(): void
    {
        $products = $this->client->products->full();

        self::assertSame($products->total, \count($products->result));
        self::assertNotSame([], $products->result[0]->releases);
    }

    public function testPhp(): void
    {
        $php = $this->client->products->get('php')?->result;

        self::assertNotNull($php);
        self::assertSame('Active Support', $php->labels->eoas);
        self::assertNotNull($php->release('8.4')?->eoasFrom);
    }

    public function testAnAliasIsRedirected(): void
    {
        self::assertSame('ubuntu', $this->client->products->get('ubuntu-linux')?->result->name);
    }

    public function testUnknownProductIsNull(): void
    {
        self::assertNull($this->client->products->get('gosuccess-no-such-product'));
    }

    public function testRelease(): void
    {
        $release = $this->client->products->release('mariadb', '11.4')?->result;

        self::assertNotNull($release);
        self::assertSame('11.4', $release->name);
        self::assertTrue($release->isLts);
    }

    public function testUnknownReleaseIsNull(): void
    {
        self::assertNull($this->client->products->release('php', '1.0'));
    }

    public function testLatestRelease(): void
    {
        $latest = $this->client->products->latestRelease('php')?->result;

        self::assertNotNull($latest);
        self::assertSame($latest->name, $this->client->products->get('php')?->result->releases[0]->name);
    }

    public function testCategories(): void
    {
        $categories = $this->client->categories->list();

        self::assertContains('os', array_map(static fn($uri): string => $uri->name, $categories->result));
        self::assertNotSame([], $this->client->categories->products('os')?->result);
    }

    public function testTags(): void
    {
        $tags = $this->client->tags->list();

        self::assertContains('linux-distribution', array_map(static fn($uri): string => $uri->name, $tags->result));
        self::assertContains('ubuntu', array_map(static fn($product): string => $product->name, $this->client->tags->products('linux-distribution')->result ?? []));
    }

    public function testIdentifiers(): void
    {
        $types = $this->client->identifiers->types();

        self::assertContains('purl', array_map(static fn($uri): string => $uri->name, $types->result));
        self::assertNotSame([], $this->client->identifiers->list('purl')?->result);
    }
}

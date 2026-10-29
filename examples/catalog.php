<?php

declare(strict_types=1);

/**
 * Prints the categories with the number of their products, and the identifier
 * types.
 *
 * Usage: php examples/catalog.php
 */

use GoSuccess\EndOfLife\EndOfLife;
use GoSuccess\EndOfLife\Exception\EndOfLifeException;

$client = require __DIR__ . '/bootstrap.php';
assert($client instanceof EndOfLife);

try {
    $products = $client->products->list();
    $counts = array_count_values(array_map(static fn($product): string => $product->category, $products->result));
    arsort($counts);

    printf("%d products, generated %s\n", $products->total, $products->generatedAt->format('Y-m-d H:i T'));

    foreach ($counts as $category => $count) {
        printf("  %-12s %d\n", $category, $count);
    }

    $types = array_map(static fn($uri): string => $uri->name, $client->identifiers->types()->result);
    printf("Identifier types: %s\n", implode(', ', $types));
} catch (EndOfLifeException $e) {
    fwrite(STDERR, 'Request failed: ' . $e->getMessage() . "\n");

    exit(1);
}

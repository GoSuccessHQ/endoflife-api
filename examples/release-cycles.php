<?php

declare(strict_types=1);

/**
 * Prints the release cycles of a product with their support dates.
 *
 * Usage: php examples/release-cycles.php [product]   (default: php)
 */

use GoSuccess\EndOfLife\EndOfLife;
use GoSuccess\EndOfLife\Exception\EndOfLifeException;

$client = require __DIR__ . '/bootstrap.php';
assert($client instanceof EndOfLife);

$name = $argv[1] ?? 'php';

try {
    $product = $client->products->get($name)?->result;
} catch (EndOfLifeException $e) {
    fwrite(STDERR, 'Request failed: ' . $e->getMessage() . "\n");

    exit(1);
}

if ($product === null) {
    fwrite(STDERR, "endoflife.date does not know {$name}.\n");

    exit(1);
}

$date = static fn(?DateTimeImmutable $date): string => $date?->format('Y-m-d') ?? '-';

printf("%s: %s / %s\n", $product->label, $product->labels->eoas ?? '-', $product->labels->eol);
printf("%-10s %-12s %-12s %-12s %s\n", 'Cycle', 'Released', 'Active until', 'EOL from', 'Latest');

foreach ($product->releases as $cycle) {
    printf(
        "%-10s %-12s %-12s %-12s %s\n",
        $cycle->name,
        $date($cycle->releaseDate),
        $date($cycle->eoasFrom),
        $date($cycle->eolFrom),
        $cycle->latest->name ?? '-',
    );
}

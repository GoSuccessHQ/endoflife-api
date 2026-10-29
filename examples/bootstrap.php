<?php

declare(strict_types=1);

/**
 * Shared setup of the examples: the autoloader and a client with the default
 * options. endoflife.date needs no API key. The examples send real requests when
 * run by hand; keep that in mind before running them in a loop.
 */

use GoSuccess\EndOfLife\EndOfLife;

require __DIR__ . '/../vendor/autoload.php';

return new EndOfLife();

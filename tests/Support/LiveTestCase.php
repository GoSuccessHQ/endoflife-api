<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Tests\Support;

use GoSuccess\EndOfLife\EndOfLife;
use PHPUnit\Framework\TestCase;

/**
 * Base of the live tests: they read the real endoflife.date API (one request
 * per endpoint) and are skipped unless ENDOFLIFE_LIVE=1.
 */
abstract class LiveTestCase extends TestCase
{
    protected EndOfLife $client;

    protected function setUp(): void
    {
        if (getenv('ENDOFLIFE_LIVE') !== '1') {
            self::markTestSkipped('Set ENDOFLIFE_LIVE=1 to query the live endoflife.date API.');
        }

        $this->client = new EndOfLife();
    }
}

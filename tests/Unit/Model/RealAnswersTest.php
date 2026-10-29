<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Tests\Unit\Model;

use GoSuccess\EndOfLife\EndOfLife;
use GoSuccess\EndOfLife\Http\Response;
use GoSuccess\EndOfLife\Model\ProductDetails;
use GoSuccess\EndOfLife\Model\ProductRelease;
use GoSuccess\EndOfLife\Model\ProductResponse;
use GoSuccess\EndOfLife\Tests\Support\MockHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Real answers of the API (tests/Fixtures) through the whole client: the
 * support dates of the PHP branches and of Ubuntu releases.
 */
#[CoversClass(ProductResponse::class)]
#[CoversClass(ProductDetails::class)]
#[CoversClass(ProductRelease::class)]
final class RealAnswersTest extends TestCase
{
    public function testPhp(): void
    {
        $response = self::product('php');
        $php = $response->result;

        self::assertSame('1.2.1', $response->schemaVersion);
        self::assertSame('2026-10-22T12:35:57+00:00', $response->lastModified->format(\DATE_ATOM));
        self::assertSame(['php', 'PHP', 'lang', 'php --version'], [$php->name, $php->label, $php->category, $php->versionCommand]);
        self::assertSame(['Active Support', 'Security Support', null, null], [$php->labels->eoas, $php->labels->eol, $php->labels->eoes, $php->labels->discontinued]);
        self::assertCount(18, $php->releases);
        self::assertSame('8.5', $php->releases[0]->name);

        $release = $php->release('8.4');
        self::assertNotNull($release);
        self::assertSame(
            ['8.4', '2024-11-21', '2026-12-31', '2028-12-31', '8.4.27', '2026-10-21'],
            [$release->name, $release->releaseDate->format('Y-m-d'), $release->eoasFrom?->format('Y-m-d'), $release->eolFrom?->format('Y-m-d'), $release->latest?->name, $release->latest?->date?->format('Y-m-d')],
        );
        self::assertSame([false, false, true], [$release->isEoas, $release->isEol, $release->isMaintained]);
        // PHP has neither extended support nor discontinuations.
        self::assertSame([null, null, null, null], [$release->isEoes, $release->eoesFrom, $release->isDiscontinued, $release->discontinuedFrom]);
    }

    public function testAnOldPhpBranch(): void
    {
        $release = self::product('php')->result->release('5.2');

        self::assertNotNull($release);
        self::assertSame([true, true, false], [$release->isEoas, $release->isEol, $release->isMaintained]);
        self::assertSame(['2008-11-02', '2011-01-06', '5.2.17'], [$release->eoasFrom?->format('Y-m-d'), $release->eolFrom?->format('Y-m-d'), $release->latest?->name]);
    }

    public function testUbuntuWithExtendedSupport(): void
    {
        $ubuntu = self::product('ubuntu')->result;

        self::assertSame(['ubuntu-linux'], $ubuntu->aliases);
        self::assertSame('Expanded Security Maintenance', $ubuntu->labels->eoes);

        $lts = $ubuntu->release('22.04');
        self::assertNotNull($lts);
        self::assertSame(['Jammy Jellyfish', true, null], [$lts->codename, $lts->isLts, $lts->ltsFrom]);
        self::assertSame(['2024-09-30', '2027-06-01', '2032-04-21'], [$lts->eoasFrom?->format('Y-m-d'), $lts->eolFrom?->format('Y-m-d'), $lts->eoesFrom?->format('Y-m-d')]);
        self::assertSame([true, false, false], [$lts->isEoas, $lts->isEol, $lts->isEoes]);

        // A release without LTS is not eligible for extended support.
        $interim = $ubuntu->release('25.04');
        self::assertNotNull($interim);
        self::assertFalse($interim->isLts);
        self::assertNull($interim->isEoes);
        self::assertNull($interim->eoesFrom);
    }

    private static function product(string $name): ProductResponse
    {
        $body = (string) file_get_contents(__DIR__ . "/../../Fixtures/{$name}.json");
        $response = new EndOfLife(httpClient: new MockHttpClient(new Response(200, $body)))->products->get($name);
        self::assertNotNull($response);

        return $response;
    }
}

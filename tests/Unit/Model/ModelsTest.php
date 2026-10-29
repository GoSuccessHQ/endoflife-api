<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Tests\Unit\Model;

use DateTimeImmutable;
use GoSuccess\EndOfLife\Exception\SerializationException;
use GoSuccess\EndOfLife\Internal\Json;
use GoSuccess\EndOfLife\Model\FullProductListResponse;
use GoSuccess\EndOfLife\Model\Identifier;
use GoSuccess\EndOfLife\Model\IdentifierListResponse;
use GoSuccess\EndOfLife\Model\IdentifierReference;
use GoSuccess\EndOfLife\Model\ProductDetails;
use GoSuccess\EndOfLife\Model\ProductLabels;
use GoSuccess\EndOfLife\Model\ProductLinks;
use GoSuccess\EndOfLife\Model\ProductListResponse;
use GoSuccess\EndOfLife\Model\ProductRelease;
use GoSuccess\EndOfLife\Model\ProductReleaseResponse;
use GoSuccess\EndOfLife\Model\ProductResponse;
use GoSuccess\EndOfLife\Model\ProductSummary;
use GoSuccess\EndOfLife\Model\ProductVersion;
use GoSuccess\EndOfLife\Model\Uri;
use GoSuccess\EndOfLife\Model\UriListResponse;
use GoSuccess\EndOfLife\Tests\Support\Spec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The models read the examples of the specification field by field, and fail
 * with the path of a field that does not match it.
 */
#[CoversClass(Json::class)]
#[CoversClass(Uri::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(IdentifierReference::class)]
#[CoversClass(ProductVersion::class)]
#[CoversClass(ProductRelease::class)]
#[CoversClass(ProductSummary::class)]
#[CoversClass(ProductDetails::class)]
#[CoversClass(ProductLabels::class)]
#[CoversClass(ProductLinks::class)]
#[CoversClass(UriListResponse::class)]
#[CoversClass(ProductListResponse::class)]
#[CoversClass(FullProductListResponse::class)]
#[CoversClass(ProductReleaseResponse::class)]
#[CoversClass(ProductResponse::class)]
#[CoversClass(IdentifierListResponse::class)]
final class ModelsTest extends TestCase
{
    public function testUriList(): void
    {
        $list = UriListResponse::fromJson(Spec::example('UriListResponse'));

        self::assertSame('1.0.0', $list->schemaVersion);
        self::assertSame('2023-03-01T13:05:52+00:00', $list->generatedAt->format(\DATE_ATOM));
        self::assertSame(3, $list->total);
        self::assertEquals([new Uri('tags', 'https://endoflife.date/tags/')], $list->result);
    }

    public function testProductList(): void
    {
        $list = ProductListResponse::fromJson(Spec::example('ProductListResponse'));

        self::assertSame(200, $list->total);
        self::assertEquals(
            [new ProductSummary('ubuntu', 'Ubuntu', ['ubuntu-linux'], 'os', ['canonical', 'os'], 'https://endoflife.date/api/v1/products/ubuntu/')],
            $list->result,
        );
    }

    public function testProduct(): void
    {
        $response = ProductResponse::fromJson(Spec::example('ProductResponse'));
        $product = $response->result;

        self::assertSame('2023-03-01T13:05:52+00:00', $response->lastModified->format(\DATE_ATOM));
        self::assertSame(['ubuntu', 'Ubuntu', ['ubuntu-linux'], 'os', ['canonical', 'os'], 'lsb_release --release'], [$product->name, $product->label, $product->aliases, $product->category, $product->tags, $product->versionCommand]);
        self::assertEquals([new Identifier('cpe:/o:canonical:ubuntu_linux', 'cpe')], $product->identifiers);
        self::assertEquals(new ProductLabels('Hardware & Maintenance', 'Discontinued', 'Maintenance & Security Support', 'Extended Security Maintenance'), $product->labels);
        self::assertEquals(new ProductLinks('https://simpleicons.org/icons/ubuntu.svg', 'https://endoflife.date/ubuntu', 'https://wiki.ubuntu.com/Releases'), $product->links);
        self::assertCount(1, $product->releases);
        self::assertSame($product->releases[0], $product->release('22.04'));
        self::assertNull($product->release('22.10'));
    }

    public function testFullProductList(): void
    {
        $list = FullProductListResponse::fromJson(Spec::example('FullProductListResponse'));

        self::assertSame(200, $list->total);
        self::assertSame('ubuntu', $list->result[0]->name);
        self::assertSame('22.04', $list->result[0]->releases[0]->name);
    }

    public function testRelease(): void
    {
        $release = ProductReleaseResponse::fromJson(Spec::example('ProductReleaseResponse'))->result;

        self::assertSame(['22.04', 'Jammy Jellyfish', "22.04 'Jammy Jellyfish' (LTS)"], [$release->name, $release->codename, $release->label]);
        self::assertSame('2022-04-21', self::date($release->releaseDate));
        self::assertTrue($release->isLts);
        self::assertSame('2022-04-21', self::date($release->ltsFrom));
        self::assertFalse($release->isEoas);
        self::assertSame('2024-09-30', self::date($release->eoasFrom));
        self::assertFalse($release->isEol);
        self::assertSame('2027-04-01', self::date($release->eolFrom));
        self::assertFalse($release->isDiscontinued);
        self::assertSame('2027-04-01', self::date($release->discontinuedFrom));
        self::assertTrue($release->isEoes);
        self::assertSame('2032-04-09', self::date($release->eoesFrom));
        self::assertTrue($release->isMaintained);
        self::assertSame(['22.04.2', '2022-04-21', 'https://wiki.ubuntu.com/JammyJellyfish/ReleaseNotes/'], [$release->latest?->name, self::date($release->latest?->date), $release->latest?->link]);
        self::assertSame(['chromeVersion' => 'M136', 'nodeVersion' => '22.15'], $release->custom);
    }

    public function testDatesAreMidnightUtc(): void
    {
        $release = ProductReleaseResponse::fromJson(Spec::example('ProductReleaseResponse'))->result;

        self::assertSame('2022-04-21T00:00:00+00:00', $release->releaseDate->format(\DATE_ATOM));
    }

    public function testIdentifierList(): void
    {
        $list = IdentifierListResponse::fromJson(Spec::example('IdentifierListResponse'));

        self::assertSame(3, $list->total);
        self::assertEquals([new IdentifierReference('cpe:/o:canonical:ubuntu_linux', new Uri('tags', 'https://endoflife.date/tags/'))], $list->result);
    }

    public function testPhasesAProductDoesNotHaveAreNull(): void
    {
        $data = self::release();

        foreach (['isEoas', 'eoasFrom', 'isDiscontinued', 'discontinuedFrom', 'isEoes', 'eoesFrom', 'custom'] as $field) {
            $data = Spec::without($data, $field);
        }

        $release = ProductRelease::fromJson($data, 'result');

        self::assertSame([null, null, null, null, null, null, null], [$release->isEoas, $release->eoasFrom, $release->isDiscontinued, $release->discontinuedFrom, $release->isEoes, $release->eoesFrom, $release->custom]);
    }

    public function testNullablesAreNull(): void
    {
        $data = self::release();

        foreach (['codename', 'ltsFrom', 'eoasFrom', 'eolFrom', 'discontinuedFrom', 'isEoes', 'eoesFrom', 'latest', 'custom'] as $field) {
            $data = Spec::with($data, $field, null);
        }

        $release = ProductRelease::fromJson($data, 'result');

        self::assertSame([null, null, null, null, null, null, null, null, null], [$release->codename, $release->ltsFrom, $release->eoasFrom, $release->eolFrom, $release->discontinuedFrom, $release->isEoes, $release->eoesFrom, $release->latest, $release->custom]);
    }

    public function testAVersionWithoutDateAndLink(): void
    {
        $version = ProductVersion::fromJson(['name' => '1.0', 'date' => null, 'link' => null], 'latest');

        self::assertEquals(new ProductVersion('1.0', null, null), $version);
    }

    public function testFieldsTheSpecificationDoesNotKnowAreIgnored(): void
    {
        $release = ProductRelease::fromJson([...self::release(), 'isSupported' => 'maybe'], 'result');

        self::assertSame('22.04', $release->name);
    }

    public function testCustomFieldsKeepAllDigitNames(): void
    {
        $release = ProductRelease::fromJson(Spec::with(self::release(), 'custom', ['2024' => 'x', 'build' => null]), 'result');

        self::assertSame(['2024' => 'x', 'build' => null], $release->custom);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidReleases(): iterable
    {
        $release = self::release();

        yield 'missing name' => [Spec::without($release, 'name'), 'Field result.name must be a string.'];
        yield 'null release date' => [Spec::with($release, 'releaseDate', null), 'Field result.releaseDate must be a date (YYYY-MM-DD).'];
        yield 'date that does not exist' => [Spec::with($release, 'eolFrom', '2027-02-30'), 'Field result.eolFrom must be a date (YYYY-MM-DD).'];
        yield 'date with time' => [Spec::with($release, 'eolFrom', '2027-04-01T00:00:00Z'), 'Field result.eolFrom must be a date (YYYY-MM-DD).'];
        yield 'date as boolean' => [Spec::with($release, 'eolFrom', false), 'Field result.eolFrom must be a date (YYYY-MM-DD).'];
        yield 'missing nullable eolFrom' => [Spec::without($release, 'eolFrom'), 'Field result.eolFrom is missing.'];
        yield 'missing nullable latest' => [Spec::without($release, 'latest'), 'Field result.latest is missing.'];
        yield 'boolean as string' => [Spec::with($release, 'isEol', 'false'), 'Field result.isEol must be a boolean.'];
        yield 'optional boolean as null' => [Spec::with($release, 'isEoas', null), 'Field result.isEoas must be a boolean.'];
        yield 'latest as string' => [Spec::with($release, 'latest', '22.04.2'), 'Field result.latest must be an object.'];
        yield 'latest without link' => [Spec::without($release, 'latest.link'), 'Field result.latest.link is missing.'];
        yield 'custom value as number' => [Spec::with($release, 'custom', ['chromeVersion' => 136]), 'Field result.custom.chromeVersion must be a string.'];
        yield 'custom as list' => [Spec::with($release, 'custom', ['M136']), 'Field result.custom must be an object.'];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidReleases')]
    public function testAReleaseThatDoesNotMatchTheSpecificationThrows(array $data, string $message): void
    {
        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage($message);

        ProductRelease::fromJson($data, 'result');
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidProducts(): iterable
    {
        $product = Spec::example('ProductResponse');

        yield 'tag as number' => [Spec::with($product, 'result.tags', ['os', 1]), 'Field result.tags[1] must be a string.'];
        yield 'aliases as object' => [Spec::with($product, 'result.aliases', ['a' => 'b']), 'Field result.aliases must be a list.'];
        yield 'release with wrong date' => [Spec::with($product, 'result.releases.0.releaseDate', '21.04.2022'), 'Field result.releases[0].releaseDate must be a date (YYYY-MM-DD).'];
        yield 'release that is no object' => [Spec::with($product, 'result.releases', ['22.04']), 'Field result.releases[0] must be an object.'];
        yield 'identifier without type' => [Spec::without($product, 'result.identifiers.0.type'), 'Field result.identifiers[0].type must be a string.'];
        yield 'labels without eol' => [Spec::without($product, 'result.labels.eol'), 'Field result.labels.eol must be a string.'];
        yield 'links without html' => [Spec::without($product, 'result.links.html'), 'Field result.links.html must be a string.'];
        yield 'last modified as date' => [Spec::with($product, 'last_modified', '2023-03-01'), 'Field last_modified must be a time after RFC 3339.'];
        yield 'missing result' => [Spec::without($product, 'result'), 'Field result must be an object.'];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidProducts')]
    public function testAProductThatDoesNotMatchTheSpecificationThrows(array $data, string $message): void
    {
        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage($message);

        ProductResponse::fromJson($data);
    }

    public function testAnIdentifierWithoutProductThrows(): void
    {
        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('Field result[0].product must be an object.');

        IdentifierListResponse::fromJson(Spec::without(Spec::example('IdentifierListResponse'), 'result.0.product'));
    }

    public function testATotalThatIsNoIntegerThrows(): void
    {
        $this->expectException(SerializationException::class);
        $this->expectExceptionMessage('Field total must be an integer.');

        UriListResponse::fromJson(Spec::with(Spec::example('UriListResponse'), 'total', '3'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function times(): iterable
    {
        yield 'offset' => ['2026-10-29T02:09:58+02:00', '2026-10-29T00:09:58.000000+00:00'];
        yield 'zulu' => ['2026-10-29T00:09:58Z', '2026-10-29T00:09:58.000000+00:00'];
        yield 'fraction' => ['2026-10-29T00:09:58.123456789Z', '2026-10-29T00:09:58.123456+00:00'];
    }

    #[DataProvider('times')]
    public function testTimesAreReadAfterRfc3339InUtc(string $value, string $expected): void
    {
        $list = UriListResponse::fromJson(Spec::with(Spec::example('UriListResponse'), 'generated_at', $value));

        self::assertSame($expected, $list->generatedAt->format('Y-m-d\TH:i:s.uP'));
    }

    /**
     * @return array<string, mixed>
     */
    private static function release(): array
    {
        $release = Spec::example('ProductReleaseResponse')['result'];
        self::assertIsArray($release);

        /** @var array<string, mixed> $release */
        return $release;
    }

    private static function date(?DateTimeImmutable $date): ?string
    {
        return $date?->format('Y-m-d');
    }
}

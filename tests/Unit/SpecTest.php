<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Tests\Unit;

use GoSuccess\EndOfLife\Tests\Support\Spec;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins every operation and every field of every schema against the committed
 * snapshot of the specification, in both directions: what the client calls and
 * reads must be in the specification as the models expect it, and whatever the
 * specification adds must be added to the client. The weekly spec-drift
 * workflow refreshes the snapshot; this test then names what changed.
 *
 * A field is `<mode> <type>`. Modes: `R` required and never null, `N` required
 * but may be null, `O` optional and never null, `ON` optional and may be null.
 * A type `list<…>+` is a list with at least one entry (`minItems: 1`).
 */
#[CoversNothing]
final class SpecTest extends TestCase
{
    /** @var array<string, array{string, string, string}> operationId => [method, path, schema of the 200 answer] */
    private const array OPERATIONS = [
        'index' => ['GET', '/', 'UriListResponse'],
        'products' => ['GET', '/products', 'ProductListResponse'],
        'products_full' => ['GET', '/products/full', 'FullProductListResponse'],
        'product' => ['GET', '/products/{product}', 'ProductResponse'],
        'product_release' => ['GET', '/products/{product}/releases/{release}', 'ProductReleaseResponse'],
        'product_release_latest' => ['GET', '/products/{product}/releases/latest', 'ProductReleaseResponse'],
        'categories' => ['GET', '/categories', 'UriListResponse'],
        'products_category' => ['GET', '/categories/{category}', 'ProductListResponse'],
        'tags' => ['GET', '/tags', 'UriListResponse'],
        'products_tag' => ['GET', '/tags/{tag}', 'ProductListResponse'],
        'identifier_types' => ['GET', '/identifiers', 'UriListResponse'],
        'identifier_by_type' => ['GET', '/identifiers/{identifier_type}', 'IdentifierListResponse'],
    ];

    private const array ENVELOPE = ['schema_version' => 'R string', 'generated_at' => 'R date-time'];

    /**
     * The fields of each schema as the models read them. A key with a dot
     * addresses an inline schema: `ProductDetails.labels` is the object of that
     * property, `IdentifierListResponse.result[]` the items of that list.
     *
     * @var array<string, array<string, string>>
     */
    private const array SCHEMAS = [
        'Uri' => ['name' => 'R string', 'uri' => 'R uri'],
        'Identifier' => ['id' => 'R string', 'type' => 'R string'],
        'UnknownProperties' => [],
        'ProductVersion' => ['name' => 'R string', 'date' => 'N date', 'link' => 'N uri'],
        'ProductRelease' => [
            'name' => 'R string', 'codename' => 'N string', 'label' => 'R string', 'releaseDate' => 'R date',
            'isLts' => 'R boolean', 'ltsFrom' => 'N date', 'isEoas' => 'O boolean', 'eoasFrom' => 'ON date',
            'isEol' => 'R boolean', 'eolFrom' => 'N date', 'isDiscontinued' => 'O boolean', 'discontinuedFrom' => 'ON date',
            'isEoes' => 'ON boolean', 'eoesFrom' => 'ON date', 'isMaintained' => 'R boolean',
            'latest' => 'N ProductVersion', 'custom' => 'ON UnknownProperties',
        ],
        'ProductSummary' => [
            'name' => 'R string', 'label' => 'R string', 'aliases' => 'R list<string>', 'category' => 'R string',
            'tags' => 'R list<string>+', 'uri' => 'R uri',
        ],
        'ProductDetails' => [
            'name' => 'R string', 'label' => 'R string', 'aliases' => 'R list<string>', 'category' => 'R string',
            'tags' => 'R list<string>+', 'versionCommand' => 'ON string', 'identifiers' => 'R list<Identifier>',
            'labels' => 'R object', 'links' => 'R object', 'releases' => 'R list<ProductRelease>',
        ],
        'ProductDetails.labels' => ['eoas' => 'ON string', 'discontinued' => 'ON string', 'eol' => 'R string', 'eoes' => 'ON string'],
        'ProductDetails.links' => ['icon' => 'ON uri', 'html' => 'R uri', 'releasePolicy' => 'ON uri'],
        'UriListResponse' => [...self::ENVELOPE, 'total' => 'R integer', 'result' => 'R list<Uri>'],
        'ProductListResponse' => [...self::ENVELOPE, 'total' => 'R integer', 'result' => 'R list<ProductSummary>'],
        'FullProductListResponse' => [...self::ENVELOPE, 'total' => 'R integer', 'result' => 'R list<ProductDetails>'],
        'ProductReleaseResponse' => [...self::ENVELOPE, 'result' => 'R ProductRelease'],
        'ProductResponse' => [...self::ENVELOPE, 'last_modified' => 'R date-time', 'result' => 'R ProductDetails'],
        'IdentifierListResponse' => [...self::ENVELOPE, 'total' => 'R integer', 'result' => 'R list<object>'],
        'IdentifierListResponse.result[]' => ['identifier' => 'R string', 'product' => 'R Uri'],
    ];

    public function testTheClientCoversEveryOperation(): void
    {
        $ids = array_map(static fn(array $operation): mixed => $operation['operation']['operationId'] ?? null, Spec::operations());

        self::assertEqualsCanonicalizing(array_keys(self::OPERATIONS), $ids);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function operations(): iterable
    {
        foreach (self::OPERATIONS as $id => [$method, $path, $schema]) {
            yield $id => [$id, $method, $path, $schema];
        }
    }

    #[DataProvider('operations')]
    public function testOperationIsPinned(string $operationId, string $method, string $path, string $schema): void
    {
        $operation = Spec::operation($operationId);

        self::assertSame([$method, $path], [$operation['method'], $operation['path']]);
        self::assertSame($schema, Spec::responseSchemaName($operationId));
    }

    public function testTheModelsCoverEverySchema(): void
    {
        $top = array_values(array_filter(array_keys(self::SCHEMAS), static fn(string $name): bool => !str_contains($name, '.')));

        self::assertEqualsCanonicalizing(Spec::schemaNames(), $top);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function schemas(): iterable
    {
        foreach (array_keys(self::SCHEMAS) as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('schemas')]
    public function testFieldsMatchTheModel(string $name): void
    {
        self::assertSame(self::SCHEMAS[$name], self::fields(self::schemaAt($name)));
    }

    public function testUnknownPropertiesAreStringsOrNull(): void
    {
        $schema = Spec::schema('UnknownProperties');

        self::assertSame('object', $schema['type'] ?? null);
        self::assertSame(['string', 'null'], Spec::at($schema, 'additionalProperties', 'type'));
    }

    /**
     * The 404 of an operation is what the client returns as null.
     */
    public function testTheLookupsAnswerNotFound(): void
    {
        $withNotFound = [];

        foreach (Spec::operations() as $operation) {
            if (Spec::at($operation['operation'], 'responses', '404') !== null) {
                $withNotFound[] = $operation['operation']['operationId'] ?? null;
            }
        }

        self::assertEqualsCanonicalizing(['product', 'product_release', 'product_release_latest', 'products_category', 'products_tag', 'identifier_by_type'], $withNotFound);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function schemaAt(string $name): array
    {
        $parts = explode('.', $name, 2);
        $schema = Spec::schema($parts[0]);

        if (!isset($parts[1])) {
            return $schema;
        }

        $property = rtrim($parts[1], '[]');
        $inner = Spec::at($schema, 'properties', $property);
        self::assertIsArray($inner, "{$name}: missing");

        if (str_ends_with($parts[1], '[]')) {
            $inner = $inner['items'] ?? null;
            self::assertIsArray($inner, "{$name}: not a list");
        }

        return Spec::resolve($inner);
    }

    /**
     * Every property of a schema as `<mode> <type>`, in the order of the
     * specification.
     *
     * @param array<array-key, mixed> $schema
     *
     * @return array<string, string>
     */
    private static function fields(array $schema): array
    {
        $required = \is_array($schema['required'] ?? null) ? $schema['required'] : [];
        $fields = [];

        foreach (\is_array($schema['properties'] ?? null) ? $schema['properties'] : [] as $name => $property) {
            self::assertIsArray($property);
            [$type, $nullable] = self::type($property);
            $mode = (\in_array($name, $required, true) ? '' : 'O') . ($nullable ? 'N' : '');
            $fields[(string) $name] = ($mode === '' ? 'R' : $mode) . " {$type}";
        }

        return $fields;
    }

    /**
     * The type of a property and whether it may be null.
     *
     * @param array<array-key, mixed> $property
     *
     * @return array{string, bool}
     */
    private static function type(array $property): array
    {
        if (\is_string($property['$ref'] ?? null)) {
            return [substr($property['$ref'], (int) strrpos($property['$ref'], '/') + 1), false];
        }

        $parts = $property['anyOf'] ?? $property['allOf'] ?? null;

        if (\is_array($parts)) {
            $types = [];
            $nullable = false;

            foreach ($parts as $part) {
                self::assertIsArray($part);
                [$type, $partNullable] = self::type($part);
                $nullable = $nullable || $partNullable || $type === 'null';

                if ($type !== 'null') {
                    $types[] = $type;
                }
            }

            return [implode('|', $types), $nullable];
        }

        $types = (array) ($property['type'] ?? []);
        $nullable = \in_array('null', $types, true);
        $type = implode('|', array_filter($types, static fn(mixed $type): bool => \is_string($type) && $type !== 'null'));

        if ($type === '') {
            return ['null', true];
        }

        if ($type === 'array') {
            $items = $property['items'] ?? [];
            self::assertIsArray($items);

            // `+`: at least one entry (`minItems: 1`).
            return ['list<' . self::type($items)[0] . '>' . (($property['minItems'] ?? 0) === 1 ? '+' : ''), $nullable];
        }

        if ($type === 'string' && \is_string($property['format'] ?? null)) {
            return [$property['format'], $nullable];
        }

        return [$type, $nullable];
    }
}

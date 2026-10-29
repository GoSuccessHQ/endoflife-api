<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Tests\Support;

use RuntimeException;
use stdClass;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads the committed snapshot of the OpenAPI document and builds example
 * answers from it: every property with the first of its `examples`, otherwise
 * a value of its type. Tests feed them to the client, so a model is tested
 * against the shape the specification documents, not a hand-written fixture.
 */
final class Spec
{
    private const string FILE = __DIR__ . '/../../resources/specs/openapi.yml';

    /** @var array<string, mixed>|null */
    private static ?array $spec = null;

    /**
     * The parsed document, read once per process.
     *
     * @return array<string, mixed>
     */
    public static function document(): array
    {
        if (self::$spec === null) {
            $parsed = Yaml::parseFile(self::FILE);

            if (!\is_array($parsed)) {
                throw new RuntimeException('The specification is not a YAML mapping.');
            }

            /** @var array<string, mixed> $parsed */
            self::$spec = $parsed;
        }

        return self::$spec;
    }

    /**
     * Method, path and definition of an operation.
     *
     * @return array{method: string, path: string, operation: array<string, mixed>}
     */
    public static function operation(string $operationId): array
    {
        foreach (self::operations() as $operation) {
            if (($operation['operation']['operationId'] ?? null) === $operationId) {
                return $operation;
            }
        }

        throw new RuntimeException("No operation {$operationId} in the specification.");
    }

    /**
     * Every operation of the document.
     *
     * @return list<array{method: string, path: string, operation: array<string, mixed>}>
     */
    public static function operations(): array
    {
        $operations = [];
        $paths = self::document()['paths'] ?? [];

        foreach (\is_array($paths) ? $paths : [] as $path => $item) {
            foreach (\is_array($item) ? $item : [] as $method => $operation) {
                if (\is_array($operation)) {
                    /** @var array<string, mixed> $operation */
                    $operations[] = ['method' => strtoupper((string) $method), 'path' => (string) $path, 'operation' => $operation];
                }
            }
        }

        return $operations;
    }

    /**
     * The name of the schema of an operation's `200` answer, e.g. `ProductResponse`.
     */
    public static function responseSchemaName(string $operationId): string
    {
        $ref = self::at(self::operation($operationId)['operation'], 'responses', '200', 'content', 'application/json', 'schema', '$ref');

        if (!\is_string($ref)) {
            throw new RuntimeException("{$operationId} has no JSON answer with a referenced schema.");
        }

        return self::refName($ref);
    }

    /**
     * A schema of `components.schemas`.
     *
     * @return array<string, mixed>
     */
    public static function schema(string $name): array
    {
        $schema = self::at(self::document(), 'components', 'schemas', $name);

        if (!\is_array($schema)) {
            throw new RuntimeException("No schema {$name} in the specification.");
        }

        /** @var array<string, mixed> $schema */
        return $schema;
    }

    /**
     * The names of all schemas of `components.schemas`.
     *
     * @return list<string>
     */
    public static function schemaNames(): array
    {
        $schemas = self::at(self::document(), 'components', 'schemas');

        return \is_array($schemas) ? array_map(strval(...), array_keys($schemas)) : [];
    }

    /**
     * A schema with `$ref` and a single `allOf` part resolved.
     *
     * @param array<array-key, mixed> $schema
     *
     * @return array<array-key, mixed>
     */
    public static function resolve(array $schema): array
    {
        if (\is_string($schema['$ref'] ?? null)) {
            return self::resolve(self::schema(self::refName($schema['$ref'])));
        }

        $parts = $schema['allOf'] ?? null;

        if (\is_array($parts) && \count($parts) === 1 && \is_array($parts[0] ?? null)) {
            unset($schema['allOf']);

            return [...$schema, ...self::resolve($parts[0])];
        }

        return $schema;
    }

    /**
     * An example answer of the schema with the given name, decoded as the client
     * decodes it.
     *
     * @return array<string, mixed>
     */
    public static function example(string $schemaName): array
    {
        $json = json_encode(self::build(self::schema($schemaName)), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
        $example = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($example)) {
            throw new RuntimeException("The example of {$schemaName} is not an object.");
        }

        /** @var array<string, mixed> $example */
        return $example;
    }

    /**
     * A copy of an answer with the value at a dotted path (`result.releases.0.name`)
     * replaced, so a test can break one field of an example.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function with(array $data, string $path, mixed $value): array
    {
        return self::stringKeys(self::replace($data, explode('.', $path), $value, false));
    }

    /**
     * A copy of an answer without the key at a dotted path.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function without(array $data, string $path): array
    {
        return self::stringKeys(self::replace($data, explode('.', $path), null, true));
    }

    /**
     * The value below nested keys, or null.
     */
    public static function at(mixed $value, string|int ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (!\is_array($value) || !\array_key_exists($key, $value)) {
                return null;
            }

            $value = $value[$key];
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string>            $keys
     *
     * @return array<array-key, mixed>
     */
    private static function replace(array $data, array $keys, mixed $value, bool $remove): array
    {
        $key = array_shift($keys) ?? '';

        if ($keys === []) {
            if ($remove) {
                unset($data[$key]);
            } else {
                $data[$key] = $value;
            }

            return $data;
        }

        $inner = $data[$key] ?? null;
        $data[$key] = self::replace(\is_array($inner) ? $inner : [], $keys, $value, $remove);

        return $data;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function stringKeys(array $data): array
    {
        $object = [];

        foreach ($data as $key => $value) {
            if (!\is_string($key)) {
                throw new RuntimeException('An answer is a JSON object; its keys are strings.');
            }

            $object[$key] = $value;
        }

        return $object;
    }

    private static function refName(string $ref): string
    {
        return substr($ref, (int) strrpos($ref, '/') + 1);
    }

    /**
     * @param array<array-key, mixed> $schema
     */
    private static function build(array $schema): mixed
    {
        $schema = self::resolve($schema);
        $examples = $schema['examples'] ?? null;

        if (\is_array($examples) && \array_key_exists(0, $examples)) {
            return $examples[0];
        }

        $first = self::at($schema, 'anyOf', 0);

        if (\is_array($first)) {
            return self::build($first);
        }

        $types = array_filter((array) ($schema['type'] ?? 'object'), static fn(mixed $type): bool => \is_string($type) && $type !== 'null');
        $type = array_values($types)[0] ?? 'null';

        return match ($type) {
            'object' => self::object($schema),
            'array' => [self::build(\is_array($schema['items'] ?? null) ? $schema['items'] : [])],
            'string' => match ($schema['format'] ?? null) {
                'date' => '2026-01-30',
                'date-time' => '2026-01-30T23:55:00+00:00',
                default => 'string',
            },
            'integer' => 1,
            'boolean' => false,
            default => null,
        };
    }

    /**
     * @param array<array-key, mixed> $schema
     *
     * @return array<string, mixed>|object
     */
    private static function object(array $schema): array|object
    {
        $object = [];

        foreach (\is_array($schema['properties'] ?? null) ? $schema['properties'] : [] as $name => $property) {
            $object[(string) $name] = \is_array($property) ? self::build($property) : null;
        }

        // An object without properties stays an object in JSON.
        return $object === [] ? new stdClass() : $object;
    }
}

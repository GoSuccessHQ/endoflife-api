<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Internal;

use DateTimeImmutable;
use DateTimeZone;
use GoSuccess\EndOfLife\Exception\SerializationException;

/**
 * Typed reads from decoded JSON objects, as strict as the specification: a
 * required field that is missing, or any field with another type, throws a
 * {@see SerializationException} that names its path (`result.releases[3].eolFrom`),
 * never the value itself. Fields the specification does not know are ignored,
 * since the API may add new ones.
 *
 * @internal
 */
final class Json
{
    /** RFC 3339 with and without fractions; `P` also accepts `Z`. */
    private const array TIME_FORMATS = ['Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP'];

    private function __construct() {}

    /**
     * Fields the specification requires although their value may be null: they
     * must be there, null or not.
     *
     * @param array<array-key, mixed> $data
     */
    public static function requireKeys(array $data, string $path, string ...$keys): void
    {
        foreach ($keys as $key) {
            if (!\array_key_exists($key, $data)) {
                throw new SerializationException('Field ' . self::join($path, $key) . ' is missing.');
            }
        }
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function string(array $data, string|int $key, string $path): string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) ? $value : throw self::invalid($path, $key, 'a string');
    }

    /**
     * A string that may be null; missing counts as null.
     *
     * @param array<array-key, mixed> $data
     */
    public static function nullableString(array $data, string|int $key, string $path): ?string
    {
        return ($data[$key] ?? null) === null ? null : self::string($data, $key, $path);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function int(array $data, string|int $key, string $path): int
    {
        $value = $data[$key] ?? null;

        return \is_int($value) ? $value : throw self::invalid($path, $key, 'an integer');
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function bool(array $data, string|int $key, string $path): bool
    {
        $value = $data[$key] ?? null;

        return \is_bool($value) ? $value : throw self::invalid($path, $key, 'a boolean');
    }

    /**
     * A boolean the specification makes optional, but never null: missing is null.
     *
     * @param array<array-key, mixed> $data
     */
    public static function optionalBool(array $data, string|int $key, string $path): ?bool
    {
        return \array_key_exists($key, $data) ? self::bool($data, $key, $path) : null;
    }

    /**
     * A boolean that may be null; missing counts as null.
     *
     * @param array<array-key, mixed> $data
     */
    public static function nullableBool(array $data, string|int $key, string $path): ?bool
    {
        return ($data[$key] ?? null) === null ? null : self::bool($data, $key, $path);
    }

    /**
     * A calendar date (`2022-04-21`, `format: date`) at midnight UTC. A date
     * that does not exist, such as `2026-02-30`, is invalid.
     *
     * @param array<array-key, mixed> $data
     */
    public static function date(array $data, string|int $key, string $path): DateTimeImmutable
    {
        $value = $data[$key] ?? null;

        if (\is_string($value)) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

            if ($date !== false && $date->format('Y-m-d') === $value) {
                return $date;
            }
        }

        throw self::invalid($path, $key, 'a date (YYYY-MM-DD)');
    }

    /**
     * A date that may be null; missing counts as null.
     *
     * @param array<array-key, mixed> $data
     */
    public static function nullableDate(array $data, string|int $key, string $path): ?DateTimeImmutable
    {
        return ($data[$key] ?? null) === null ? null : self::date($data, $key, $path);
    }

    /**
     * A point in time after RFC 3339 (`format: date-time`), in UTC.
     *
     * @param array<array-key, mixed> $data
     */
    public static function time(array $data, string|int $key, string $path): DateTimeImmutable
    {
        $value = $data[$key] ?? null;

        if (\is_string($value)) {
            // PHP reads at most six fractional digits; RFC 3339 allows more.
            $value = (string) preg_replace('/(\.\d{6})\d+/', '$1', $value, 1);

            foreach (self::TIME_FORMATS as $format) {
                $time = DateTimeImmutable::createFromFormat("!{$format}", $value);
                $errors = DateTimeImmutable::getLastErrors();

                // Warnings mean an overflowing date (February 30th), which PHP would move.
                if ($time !== false && ($errors === false || $errors['warning_count'] === 0)) {
                    return $time->setTimezone(new DateTimeZone('UTC'));
                }
            }
        }

        throw self::invalid($path, $key, 'a time after RFC 3339');
    }

    /**
     * A JSON object; `{}` decodes to an empty array and is one.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function object(array $data, string|int $key, string $path): array
    {
        $value = $data[$key] ?? null;

        if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
            throw self::invalid($path, $key, 'an object');
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * An object that may be null; missing counts as null.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<string, mixed>|null
     */
    public static function nullableObject(array $data, string|int $key, string $path): ?array
    {
        return ($data[$key] ?? null) === null ? null : self::object($data, $key, $path);
    }

    /**
     * A list of objects.
     *
     * @param array<array-key, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    public static function list(array $data, string|int $key, string $path): array
    {
        $items = [];

        foreach (self::array($data, $key, $path) as $index => $item) {
            $items[] = self::object([$index => $item], $index, self::join($path, $key));
        }

        return $items;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<string>
     */
    public static function strings(array $data, string|int $key, string $path): array
    {
        $items = [];

        foreach (self::array($data, $key, $path) as $index => $item) {
            $items[] = self::string([$index => $item], $index, self::join($path, $key));
        }

        return $items;
    }

    /**
     * A list of strings with at least one entry (`minItems: 1`).
     *
     * @param array<array-key, mixed> $data
     *
     * @return non-empty-list<string>
     */
    public static function nonEmptyStrings(array $data, string|int $key, string $path): array
    {
        $items = self::strings($data, $key, $path);

        return $items !== [] ? $items : throw self::invalid($path, $key, 'a list with at least one string');
    }

    /**
     * An object of strings or nulls (`UnknownProperties`), or null; missing
     * counts as null.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<string, string|null>|null
     */
    public static function nullableStringMap(array $data, string|int $key, string $path): ?array
    {
        $object = self::nullableObject($data, $key, $path);

        if ($object === null) {
            return null;
        }

        $map = [];

        foreach ($object as $name => $value) {
            // PHP turns an all-digit key into an int.
            $map[(string) $name] = self::nullableString($object, $name, self::join($path, $key));
        }

        return $map;
    }

    /**
     * The path of a value below `$path`: `a.b` for keys, `a[3]` for list indexes.
     */
    public static function join(string $path, string|int $key): string
    {
        if (\is_int($key)) {
            return "{$path}[{$key}]";
        }

        return $path === '' ? $key : "{$path}.{$key}";
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<mixed>
     */
    private static function array(array $data, string|int $key, string $path): array
    {
        $value = $data[$key] ?? null;

        if (!\is_array($value) || !array_is_list($value)) {
            throw self::invalid($path, $key, 'a list');
        }

        return $value;
    }

    private static function invalid(string $path, string|int $key, string $expected): SerializationException
    {
        // A list item's path ends with [index]: $path already names its list.
        $field = \is_int($key) ? "{$path}[{$key}]" : self::join($path, $key);

        return new SerializationException("Field {$field} must be {$expected}.");
    }
}

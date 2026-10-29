<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Exception;

use RuntimeException;

/**
 * A successful response does not match the specification: its body is no JSON
 * object, or a field the specification requires is missing or has another type.
 * The message names the request and the field (`result.releases[3].eolFrom`),
 * never its value.
 */
final class SerializationException extends RuntimeException implements EndOfLifeException {}

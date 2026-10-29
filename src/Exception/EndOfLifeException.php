<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Exception;

use Throwable;

/**
 * Marker interface implemented by every exception thrown by this library, so
 * callers can catch all of them with a single `catch (EndOfLifeException $e)`.
 */
interface EndOfLifeException extends Throwable {}

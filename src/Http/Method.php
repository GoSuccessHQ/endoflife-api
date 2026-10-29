<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Http;

/**
 * HTTP methods of the API; it only reads.
 */
enum Method: string
{
    case Get = 'GET';
}

<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Exception;

/**
 * The API or Netlify, which hosts it, failed to process the request (HTTP 5xx),
 * also after the retries.
 */
final class ServerException extends ApiException {}

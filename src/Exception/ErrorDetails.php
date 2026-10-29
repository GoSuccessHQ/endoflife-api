<?php

declare(strict_types=1);

namespace GoSuccess\EndOfLife\Exception;

/**
 * Reads a short message from an error body. The API has no error format: a `404`
 * is the HTML error page of the site, and Netlify answers its own errors in
 * plain text. HTML adds nothing to the message; a JSON `message` or `error`, or
 * plain text, is shortened.
 *
 * @internal
 */
final class ErrorDetails
{
    private const int MAX_MESSAGE_BYTES = 300;

    private function __construct() {}

    public static function message(string $body): ?string
    {
        $body = trim($body);

        if ($body === '' || str_starts_with($body, '<')) {
            return null;
        }

        $decoded = json_decode($body, true);

        if (\is_array($decoded)) {
            $text = $decoded['message'] ?? $decoded['error'] ?? null;

            return \is_string($text) && trim($text) !== '' ? self::excerpt($text) : null;
        }

        return self::excerpt($body);
    }

    private static function excerpt(string $text): string
    {
        // One line, so a log entry stays one line.
        $text = trim((string) preg_replace('/\s+/', ' ', $text));

        if (\strlen($text) <= self::MAX_MESSAGE_BYTES) {
            return $text;
        }

        $cut = substr($text, 0, self::MAX_MESSAGE_BYTES);

        // Drop a trailing, incomplete multi-byte sequence (ext-mbstring is not required).
        return (preg_replace('/[\xC0-\xFF][\x80-\xBF]*$/', '', $cut) ?? $cut) . '…';
    }
}

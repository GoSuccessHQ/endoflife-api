<?php

declare(strict_types=1);

/**
 * Refreshes the snapshot of the endoflife.date API specification in
 * resources/specs. The weekly spec-drift workflow runs it and fails when the
 * snapshot changed, so a change to an operation or field the client uses is
 * noticed (SpecTest checks the snapshot).
 */

use GoSuccess\EndOfLife\Http\CurlHttpClient;
use GoSuccess\EndOfLife\Http\Method;
use GoSuccess\EndOfLife\Http\Request;

require __DIR__ . '/../vendor/autoload.php';

$sources = [
    'openapi.yml' => 'https://endoflife.date/docs/api/v1/openapi.yml',
];

$http = new CurlHttpClient();

foreach ($sources as $file => $url) {
    $response = $http->send(new Request(Method::Get, $url, ['Accept' => 'application/yaml']));

    if (!$response->isSuccessful || trim($response->body) === '') {
        fwrite(STDERR, "{$url}: HTTP {$response->statusCode}\n");

        exit(1);
    }

    // Kept as published: the YAML is stable, so a diff shows what changed.
    file_put_contents(__DIR__ . "/../resources/specs/{$file}", $response->body);
    echo "{$file}\n";
}

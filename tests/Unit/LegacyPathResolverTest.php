<?php

declare(strict_types=1);

use App\Domain\Publishing\Services\LegacyPathResolver;

/**
 * Golden parity: each case is pinned to the output of the JS resolveHistoricPath()
 * in src/data/redirects.mjs. If the JS ever changes, this table changes with it —
 * the PHP port must never diverge silently.
 */
beforeEach(function () {
    $this->resolver = new LegacyPathResolver;
});

dataset('historic_paths', [
    // [input, expected]
    'root is not historic' => ['/', null],
    'empty string' => ['', null],
    'index.html → home' => ['/index.html', '/'],
    'index.htm → home' => ['/index.htm', '/'],
    'soe.htm → schedule' => ['/soe.htm', '/schedule'],
    'soe.html → schedule' => ['/soe.html', '/schedule'],

    // City remaps (CITY_TO_2026_SLUG).
    'charlotte slash' => ['/charlotte/', '/city/charlotte'],
    'charlotte html' => ['/charlotte.html', '/city/charlotte'],
    'honolulu → honolulu-hilo' => ['/honolulu/', '/city/honolulu-hilo'],
    'hilo → honolulu-hilo' => ['/hilo.html', '/city/honolulu-hilo'],
    'omaha' => ['/omaha/', '/city/omaha'],

    // Known historic cities → schedule.
    'boston html → schedule' => ['/boston.html', '/schedule'],
    'boston slash → schedule' => ['/boston/', '/schedule'],
    'desmoines → schedule' => ['/desmoines/', '/schedule'],
    'uppercase boston' => ['/BOSTON.HTML', '/schedule'],

    // normalizeCitySegment quirks.
    'trailing 4-digit year stripped' => ['/honolulu2024.html', '/city/honolulu-hilo'],
    'trailing dots stripped' => ['/boston.../', '/schedule'],
    'query string ignored' => ['/charlotte/?utm=x', '/city/charlotte'],
    'fragment ignored' => ['/charlotte/#top', '/city/charlotte'],

    // Non-matches.
    'unknown city' => ['/nowheresville/', null],
    'single-char segment too short' => ['/a/', null],
    'deep unknown path' => ['/foo/bar/baz/', null],
]);

it('matches the JS resolveHistoricPath output', function (string $input, ?string $expected) {
    expect($this->resolver->resolve($input))->toBe($expected);
})->with('historic_paths');

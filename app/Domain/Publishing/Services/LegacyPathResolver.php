<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Services;

/**
 * Verbatim PHP port of the legacy `resolveHistoricPath()` from
 * `src/data/redirects.mjs` (the Astro/Vercel edge middleware). This is the fuzzy
 * resolver for historic Navy Week city URLs (e.g. `/Boston.html`, `/charlotte/`)
 * that maps them to a surviving `/city/<slug>/` page or the `/schedule` hub.
 *
 * Behaviour is pinned against the JS original by LegacyPathResolverTest — do not
 * "improve" it; parity is the contract.
 */
final class LegacyPathResolver
{
    /** @var array<string, string> */
    private const CITY_TO_2026_SLUG = [
        'charlotte' => 'charlotte',
        'harrisburg' => 'harrisburg',
        'omaha' => 'omaha',
        'billings' => 'billings',
        'burlington' => 'burlington',
        'cincinnati' => 'cincinnati',
        'flagstaff' => 'flagstaff',
        'lexington' => 'lexington',
        'honolulu' => 'honolulu-hilo',
        'hilo' => 'honolulu-hilo',
    ];

    /** @var list<string> */
    private const KNOWN_HISTORIC_CITIES = [
        'albuquerque', 'atlanta', 'austin', 'baltimore', 'boise', 'boston',
        'buffalo', 'chattanooga', 'chicago', 'cleveland', 'columbus', 'dallas',
        'denver', 'desmoines', 'detroit', 'greenville', 'houston',
        'indianapolis', 'kansascity', 'knoxville', 'littlerock', 'losangeles',
        'milwaukee', 'neworleans', 'okc', 'philadelphia', 'phoenix',
        'quadcities', 'reno', 'rochester', 'rockcounty', 'sacramento',
        'salinas', 'saltlakecity', 'sanantonio', 'spokane', 'stlouis',
        'tampa', 'toledo', 'twincities', 'york',
    ];

    /**
     * @return string|null the historic target path (no trailing slash), or null
     *                     when the path is not a recognised legacy URL
     */
    public function resolve(string $rawPath): ?string
    {
        // decodeURIComponent with fallback to the raw string.
        $path = rawurldecode($rawPath);
        $path = trim(mb_strtolower($path, 'UTF-8'));

        if ($path === '/' || $path === '') {
            return null;
        }

        // Strip query string / fragment.
        if (preg_match('/[?#]/', $path, $m, PREG_OFFSET_CAPTURE)) {
            $path = substr($path, 0, $m[0][1]);
        }

        // Strip trailing slashes.
        $path = (string) preg_replace('#/+$#', '', $path);
        if ($path === '') {
            return null;
        }

        if ($path === '/index.html' || $path === '/index.htm') {
            return '/';
        }
        if ($path === '/soe.htm' || $path === '/soe.html') {
            return '/schedule';
        }

        $segments = array_values(array_filter(explode('/', $path), static fn (string $s): bool => $s !== ''));
        if ($segments === []) {
            return null;
        }

        $cityKey = $this->normalizeCitySegment($segments[0]);
        if ($cityKey === '' || mb_strlen($cityKey) < 2) {
            return null;
        }

        if (isset(self::CITY_TO_2026_SLUG[$cityKey])) {
            return '/city/'.self::CITY_TO_2026_SLUG[$cityKey];
        }

        if (in_array($cityKey, self::KNOWN_HISTORIC_CITIES, true)) {
            return '/schedule';
        }

        return null;
    }

    private function normalizeCitySegment(string $segment): string
    {
        $s = rawurldecode(mb_strtolower($segment, 'UTF-8'));
        $s = (string) preg_replace('/\.html?$/', '', $s);       // trailing .htm(l)
        $s = (string) preg_replace('/[%\s\x{00a0}]+/u', '', $s); // %, whitespace, nbsp
        $s = (string) preg_replace('/[.\x{2026}]+$/u', '', $s);  // trailing dots / ellipsis
        $s = (string) preg_replace('/\d{4}$/', '', $s);          // trailing 4-digit year
        $s = (string) preg_replace('/[.\x{2026}]+$/u', '', $s);  // trailing dots again

        return trim($s);
    }
}

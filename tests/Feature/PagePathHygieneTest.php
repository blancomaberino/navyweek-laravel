<?php

declare(strict_types=1);

/**
 * Fail-closed architecture guard for URL-path hygiene. A page schema or a page
 * generator may only spell a route-shaped string literal that is on the explicit
 * ALLOWLIST below (genuinely-fixed routes + one-off content pages). Everything else
 * must come from `$page->url_path` / `App\Domain\Publishing\Support\PagePaths` (→ config).
 *
 * Why fail-closed matters: a DENYLIST (of the families we already know) would let a
 * BRAND-NEW family hardcode its own new prefix and pass silently — exactly the gap that
 * let the original bug ship. With an allowlist, a new family's hardcoded path fails HERE
 * until the author either routes it through PagePaths (the fix) or consciously adds the
 * route to the allowlist (a reviewed decision that it is genuinely fixed / one-off).
 *
 * Scans STRING LITERALS only (token_get_all), so doc-comment examples and regex patterns
 * (which contain spaces / don't match the route shape) don't trip it.
 */

/**
 * Route-shaped string literals in the given files, as [ "file:line" => literalValue ].
 * "Route-shaped" = starts with a lowercase/hyphen path segment then a slash
 * (e.g. "/navy-bases/", "/og/x.png"); excludes regexes ("/blue angels/i" — space) and
 * files ("/favicon.svg" — no segment slash).
 */
function routeShapedLiterals(string $glob): array
{
    $out = [];
    foreach (glob($glob) ?: [] as $file) {
        foreach (token_get_all((string) file_get_contents($file)) as $token) {
            if (! is_array($token) || ! in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                continue;
            }
            $value = trim($token[1], "\"'");
            if (preg_match('#^/[a-z0-9-]+/#', $value) === 1) {
                $out[basename($file).':'.$token[2]] = $value;
            }
        }
    }

    return $out;
}

/** True when the literal falls under one of the allowlisted prefixes. */
function isAllowlistedRoute(string $value, array $allow): bool
{
    foreach ($allow as $prefix) {
        if (str_starts_with($value, $prefix)) {
            return true;
        }
    }

    return false;
}

it('page schemas spell no route literal outside the allowlist', function () {
    // Fixed, non-family routes a schema may legitimately reference.
    $allow = ['/authors/', '/og/', '/schedule/'];

    $violations = [];
    foreach (routeShapedLiterals(app_path('Domain/*/Seo/*Schema.php')) as $loc => $value) {
        if (! isAllowlistedRoute($value, $allow)) {
            $violations[] = "{$loc}  {$value}";
        }
    }
    expect($violations)->toBe(
        [],
        'A schema hardcodes a route literal. Build the page URL from $page->url_path and '
        .'family/ancestor links via PagePaths, or (if the route is genuinely fixed) add it '
        .'to the allowlist in this test. Offenders:'.PHP_EOL.implode(PHP_EOL, $violations)
    );
});

it('page generators spell no route literal outside the allowlist', function () {
    // /og/ image assets + the one-off content pages that legitimately own a fixed path.
    // A NEW page family must NOT appear here — it belongs in config('publishing.paths')
    // and must build its url_path via PagePaths.
    // (The home root `/` is also a reviewed one-off but needs no entry: it isn't
    // route-shaped, so the regex above never collects it — GenerateHomePageAction.)
    $allow = ['/og/', '/privacy/', '/terms/', '/contact/', '/va-disability/', '/veterans-day/', '/veterans-home-care/'];

    $violations = [];
    foreach (routeShapedLiterals(app_path('Domain/*/Pages/*Action.php')) as $loc => $value) {
        if (! isAllowlistedRoute($value, $allow)) {
            $violations[] = "{$loc}  {$value}";
        }
    }
    expect($violations)->toBe(
        [],
        'A generator hardcodes a route literal. A new page family belongs in '
        .'config(\'publishing.paths\') with its default path built via PagePaths (keyed on a '
        .'stable generation_key); only a genuine one-off content page is added to the '
        .'allowlist in this test. Offenders:'.PHP_EOL.implode(PHP_EOL, $violations)
    );
});

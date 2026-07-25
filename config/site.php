<?php

declare(strict_types=1);

return [
    // Canonical host + apex, ported from middleware.ts. The apex → www 301 only
    // fires when the request host equals `apex_host` (belt-and-suspenders; the
    // primary mechanism is the edge/DNS config).
    'canonical_host' => env('SITE_CANONICAL_HOST', 'www.navyweek.org'),
    'apex_host' => env('SITE_APEX_HOST', 'navyweek.org'),

    // Live route prefixes that must never be handed to the fuzzy LegacyPathResolver
    // (verbatim from middleware.ts MODERN_ROUTE_PREFIXES).
    'modern_route_prefixes' => [
        '/schedule', '/map', '/city/', '/privacy', '/terms', '/contact', '/va-disability',
        '/navy-reference', '/authors/', '/navy-bases', '/navy-ranks', '/navy-designators',
        '/navy-ratings', '/assets/', '/images/', '/insignia/', '/og/',
    ],
];

<?php

declare(strict_types=1);

return [
    // Canonical host + apex, ported from middleware.ts. The apex → www 301 only
    // fires when the request host equals `apex_host` (belt-and-suspenders; the
    // primary mechanism is the edge/DNS config).
    'canonical_host' => env('SITE_CANONICAL_HOST', 'www.navyweek.org'),
    'apex_host' => env('SITE_APEX_HOST', 'navyweek.org'),

    // Absolute canonical origin + brand, ported from src/lib/seo.ts. Every SEO tag
    // (canonical, og:url, JSON-LD @id/url, alternate feeds) is built from `url`, so
    // it must stay the https www origin the legacy site emitted byte-for-byte.
    'url' => env('SITE_URL', 'https://www.navyweek.org'),
    'name' => 'NavyWeek.org',
    'default_og_image' => '/og/home.png',

    // PostHog product analytics, ported from src/components/PostHog.astro. The
    // project key is a PUBLIC client-side key by design (shipped in the browser).
    // Ingestion goes through the first-party reverse proxy so it survives blockers.
    'posthog' => [
        'key' => env('POSTHOG_KEY', 'phc_APq8mnGVLPfULJRaxAJB7WPCZutBPBbeftqTmtdZ9qrM'),
        'host' => env('POSTHOG_HOST', 'https://t.navyweek.org'),
        'ui_host' => env('POSTHOG_UI_HOST', 'https://us.posthog.com'),
    ],

    // Live route prefixes that must never be handed to the fuzzy LegacyPathResolver
    // (verbatim from middleware.ts MODERN_ROUTE_PREFIXES).
    'modern_route_prefixes' => [
        '/schedule', '/map', '/city/', '/privacy', '/terms', '/contact', '/va-disability',
        '/navy-reference', '/authors/', '/navy-bases', '/navy-ranks', '/navy-designators',
        '/navy-ratings', '/assets/', '/images/', '/insignia/', '/og/',
    ],
];

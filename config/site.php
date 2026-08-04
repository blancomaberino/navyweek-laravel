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

    // Site-wide "Last updated" timestamp shown in the header banner + footer,
    // ported from the legacy `meta.lastUpdated`. Set to the build clock (ISO 8601
    // with the America/New_York offset) on every build; overridable per-env.
    'last_updated' => env('SITE_LAST_UPDATED', '2026-08-04T12:00:00-04:00'),

    // The default byline. New discount pages (and any page missing an assignment)
    // fall back to the `users` rows with these profile slugs as author + reviewer —
    // seeded by EditorialTeamSeeder. Authors/reviewers are otherwise assigned
    // per-page from the admin panel; this is only the initial default.
    'editorial' => [
        'default_author_slug' => env('SITE_DEFAULT_AUTHOR_SLUG', 't-alford'),
        'default_reviewer_slug' => env('SITE_DEFAULT_REVIEWER_SLUG', 'erik-rivera'),
    ],

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

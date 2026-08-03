<?php

declare(strict_types=1);

// Canonical URL prefixes for each generated page family. This is the SINGLE knob for
// a family-wide path change: edit a root here, re-run `pages:generate-*`, and every
// non-custom page in that family moves to the new prefix — a 301 from each old path
// is created automatically (PageUrlChanged → CreateRedirectListener). Pages an editor
// has renamed in the admin panel (`url_path_is_custom = true`) are preserved on re-run.
//
// A generated page's identity is its `generation_key` (stable, assigned by the
// generator), NOT its `url_path` — so a page survives BOTH a per-page rename and a
// family-wide prefix change. Both the generators (which seed `url_path`) and the SEO
// schemas (which build breadcrumb-ancestor and hub-link URLs) read these prefixes via
// `App\Domain\Publishing\Support\PagePaths`, so there is one source of truth.
//
// Jet-team paths are data-driven (`JetTeam.base_path`, editable per team) and so are
// intentionally NOT listed here; content pages (veterans-day, privacy, …) are one-off
// and own their full path.

return [
    'paths' => [
        'bases' => env('PATH_BASES', '/navy-bases/'),
        'ranks' => env('PATH_RANKS', '/navy-ranks/'),
        'ratings' => env('PATH_RATINGS', '/navy-ratings/'),
        'air_shows' => env('PATH_AIR_SHOWS', '/air-show/'),
        'fleet_weeks' => env('PATH_FLEET_WEEKS', '/fleetweek/'),
        'navy_week_cities' => env('PATH_NAVY_WEEK_CITIES', '/city/'),
        'local_discounts' => env('PATH_LOCAL_DISCOUNTS', '/discounts/'),
        'discounts' => env('PATH_DISCOUNTS', '/discount/'),
    ],
];

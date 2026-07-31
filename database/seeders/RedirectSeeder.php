<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Publishing\Enums\RedirectMatchType;
use App\Domain\Publishing\Models\Redirect;
use Illuminate\Database\Seeder;

/**
 * Seeds the hand-coded 301s from the legacy `middleware.ts` as `redirects` rows.
 * Idempotent (keyed on (from_path, match_type)). Paths are stored lowercase and trailing-slash-
 * normalized, because CanonicalUrlMiddleware matches after its trailing-slash 301.
 *
 * NOT seeded here (they are algorithmic, handled in code, not data):
 *  - apex → www, trailing slash
 *  - /discount/category/<slug>/  → computed in the middleware
 *  - the fuzzy historic-city resolve → LegacyPathResolver
 */
class RedirectSeeder extends Seeder
{
    public function run(): void
    {
        // Exact rules — the incoming path is already trailing-slash-normalized.
        $exact = [
            // AutoZone: retired flat URL + first-cut nested slugs → final nested URLs.
            ['/discount/autozone-military-discount/', '/discount/autozone/military-veteran/', 'retirement'],
            ['/discount/autozone/military-discount/', '/discount/autozone/military-veteran/', 'slug-change'],
            ['/discount/autozone/law-enforcement-discount/', '/discount/autozone/law-enforcement/', 'slug-change'],

            // Promo-code section folded into each brand's discount guide (2026-07-13).
            ['/promo-code/chewy/', '/discount/chewy-military-discount/', 'retirement'],
            ['/promo-code/doordash/', '/discount/doordash-military-discount/', 'retirement'],

            // Renamed local-discount slug (2026-07-23).
            ['/discounts/texas/houston/houston-zoo/', '/discounts/texas/houston/houston-zoo-military-veteran/', 'slug-change'],
        ];

        foreach ($exact as [$from, $to, $reason]) {
            $this->upsert($from, $to, RedirectMatchType::Exact, $reason);
        }

        // Prefix collapses (2026-07-23). Longest prefix wins, so the ratings mirror
        // under /navy-ranks/enlisted/ratings beats the generic /navy-ranks/ rule.
        $prefix = [
            ['/navy-ranks/enlisted/ratings', '/navy-ratings/', 'retirement'],
            ['/navy-ranks/', '/navy-ranks/', 'retirement'],
            ['/navy-ratings/', '/navy-ratings/', 'retirement'],
        ];

        foreach ($prefix as [$from, $to, $reason]) {
            $this->upsert($from, $to, RedirectMatchType::Prefix, $reason);
        }
    }

    private function upsert(string $from, string $to, RedirectMatchType $matchType, string $reason): void
    {
        // Key on (from_path, match_type) to match the composite unique — a path may
        // carry both an exact and a prefix rule, so from_path alone no longer
        // identifies a row.
        Redirect::query()->updateOrCreate(
            ['from_path' => $from, 'match_type' => $matchType],
            [
                'to_path' => $to,
                'status' => 301,
                'reason' => $reason,
                'is_active' => true,
            ],
        );
    }
}

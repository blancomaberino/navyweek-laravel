<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Support;

use App\Domain\Catalog\Enums\MealRedemption;
use App\Domain\Catalog\Models\VeteransDayMeal;
use App\Domain\Publishing\Seo\SeoUrl;
use App\Domain\Publishing\Support\FaqItem;
use App\Domain\Publishing\Support\PagePaths;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Derives the live, data-driven pieces of the `/veterans-day/free-meals/` roundup —
 * the headline stats, the freshest verification date, the JSON-LD ItemList entries,
 * and the FAQ answers that interpolate those stats — from the already-gated
 * `verified()` meals. Ported from `computeStats()` + the inline graph/FAQ builders in
 * the legacy `src/page-views/VeteransDayFreeMeals.tsx`. Pure: no DB access of its own.
 */
final class VeteransDayFreeMealsPresenter
{
    /** @param  Collection<int, VeteransDayMeal>  $meals  The verified roundup, brand-ordered. */
    public function __construct(private readonly Collection $meals) {}

    /**
     * Headline counts, computed live (never hardcoded) exactly as the legacy does.
     *
     * @return array{verified: int, nationwide: int, participating: int, takeout: int, sourceDomains: int}
     */
    public function stats(): array
    {
        $verified = $this->meals->count();
        $nationwide = $this->meals->where('nationwide', true)->count();

        return [
            'verified' => $verified,
            'nationwide' => $nationwide,
            'participating' => $verified - $nationwide,
            'takeout' => $this->meals
                ->filter(static fn (VeteransDayMeal $m): bool => $m->redemption !== MealRedemption::DineIn)
                ->count(),
            'sourceDomains' => $this->meals
                ->map(static fn (VeteransDayMeal $m): string => self::registrableHost($m->source_url))
                ->unique()
                ->count(),
        ];
    }

    /** The Article `dateModified` — the freshest meal verification (Y-m-d), or '' when empty. */
    public function dateModified(): string
    {
        $latest = $this->meals
            ->map(static fn (VeteransDayMeal $m): string => $m->last_verified_at->format('Y-m-d'))
            ->max();

        return is_string($latest) ? $latest : '';
    }

    /** The "Last updated: June 29, 2026" label (long-form of {@see dateModified()}). */
    public function lastUpdatedLabel(): string
    {
        $iso = $this->dateModified();
        if ($iso === '') {
            return '';
        }

        return Carbon::parse($iso)->format('F j, Y');
    }

    /**
     * ItemList sources in render order: `"{brand} — {offer}"` linked to the brand's
     * `/discount/` guide when it has one (via the paths knob), else its primary source.
     *
     * @return list<array{name: string, url: string}>
     */
    public function itemListEntries(): array
    {
        return array_values($this->meals->map(static fn (VeteransDayMeal $m): array => [
            'name' => "{$m->brand} — {$m->offer}",
            'url' => self::mealUrl($m),
        ])->all());
    }

    /** The link for a meal: its `/discount/` guide when `discount_slug` is set, else the source URL. */
    public static function mealUrl(VeteransDayMeal $meal): string
    {
        if ($meal->discount_slug !== null && $meal->discount_slug !== '') {
            return SeoUrl::absolute(PagePaths::child('discounts', $meal->discount_slug));
        }

        return $meal->source_url;
    }

    /**
     * The 7 FAQs, verbatim from the legacy roundup — Q1 and Q6 interpolate the live
     * stats and the first six (brand-ordered) brand names.
     *
     * @return list<FaqItem>
     */
    public function faqs(): array
    {
        $stats = $this->stats();
        $verified = $stats['verified'];
        $firstSix = $this->meals->take(6)->map(static fn (VeteransDayMeal $m): string => $m->brand)->implode(', ');

        return [
            new FaqItem(
                'What restaurants give free meals to veterans on Veterans Day 2026?',
                "On Veterans Day 2026 (Wednesday, November 11, 2026), national chains including {$firstSix} and others offer a free meal or free food item to veterans and, at most chains, active-duty service members. NavyWeek lists {$verified} offers, each verified against the brand's own official source. Offers and participating locations change every year, so we re-verify the full list against each brand's site every September.",
            ),
            new FaqItem(
                'Who counts as a "veteran" for these free-meal offers?',
                'It varies by brand, and each row on this page shows exactly who that brand says qualifies. Most chains extend the Veterans Day offer to both veterans and active-duty service members; many also include Reserve, National Guard, and retirees. We only list the eligibility a brand states on its own page — if a brand does not say dependents or family members qualify, we do not claim they do.',
            ),
            new FaqItem(
                'What proof do I need to get a free Veterans Day meal?',
                'Bring a form of military identification. Commonly accepted proof includes a military ID (CAC), Veterans Health Identification Card (VHIC), VA ID card, DD‑214, a Veteran ID Card, or in some cases a military uniform. The exact proof each brand asks for is listed in the "Proof required" column for that offer. When in doubt, check the brand\'s official page (linked in the Source column) before you go.',
            ),
            new FaqItem(
                'Are these free meals dine-in only, or can I get takeout?',
                'It depends on the chain. Many Veterans Day free meals are dine-in only, while some chains also offer the deal for takeout or carryout. Each row shows whether the offer is dine-in, takeout, or both. Because terms change yearly, confirm with your local restaurant — participating locations sometimes set their own rules.',
            ),
            new FaqItem(
                'Do all locations participate in the Veterans Day free meal offer?',
                'Not always. Some offers are honored at every location nationwide, while many are "at participating locations only," meaning individual franchises decide whether to take part. Each offer here is tagged nationwide or participating-locations-only. We recommend calling ahead to your local restaurant to confirm before you visit.',
            ),
            new FaqItem(
                'How does NavyWeek verify these offers?',
                "Every offer on this page is gated against a primary source — the brand's own website or official announcement — not a media roundup. We do not list an offer unless we can tie it to that brand's official source, and each row carries a visible \"Verified\" badge showing the month and year we last checked it. The full list is re-verified against each brand's official source every September, ahead of the November Veterans Day rush. As of this update we have {$verified} verified offers drawn from {$stats['sourceDomains']} official brand sources.",
            ),
            new FaqItem(
                'When is Veterans Day 2026?',
                'Veterans Day 2026 is Wednesday, November 11, 2026. Veterans Day is always observed on November 11, regardless of the day of the week. A few chains run their offer for more than one day (for example, the weekend around November 11) — where that is the case, it is noted in the offer\'s row.',
            ),
        ];
    }

    /** The registrable host of a URL (lowercased, `www.` stripped) — for the distinct-source count. */
    private static function registrableHost(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? preg_replace('/^www\./', '', strtolower($host)) ?? $host : $url;
    }
}

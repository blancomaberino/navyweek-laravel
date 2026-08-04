<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Pages;

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use Illuminate\Support\Carbon;

/**
 * Seeds the DB-driven **content** pages with their initial CMS body — the editorial
 * pages whose prose lives in `pages.body_blocks` (editable in Filament), not derived
 * from a data registry. This foundation slice ships the Breadcrumb-only legal/utility
 * pages (`/privacy/`, `/terms/`, `/contact/`); the richer YMYL pages (veterans-day,
 * va-disability, veterans-home-care) land in follow-up slices with their Article/Person/
 * WebPage graphs.
 *
 * Idempotent: upserts by the stable `generation_key` ("content:{slug}"), so the build
 * clock preserves `date_published`, an editor's later body edits are NOT clobbered on
 * re-run, and a page an editor renamed is still recognized (the seed only establishes the
 * initial content for a page that doesn't exist yet; a present page keeps its body/path).
 */
final class GenerateContentPagesAction
{
    public function __construct(
        private readonly PageRepositoryInterface $pages,
    ) {}

    public function __invoke(): int
    {
        $now = Carbon::now();
        $count = 0;

        foreach ($this->seedPages() as $seed) {
            $generationKey = "content:{$seed['slug']}";
            // Track identity by generation_key, not path — so a page an editor renamed is
            // still recognized as existing and its body is NOT re-seeded/clobbered. Fall
            // back to a path lookup for a pre-generation_key (keyless) legacy row.
            $page = $this->pages->findByGenerationKey($generationKey)
                ?? $this->pages->findPublishedByPath($seed['url_path']);

            // Don't clobber an editor's body on re-run — only seed a page that's new or
            // has no body yet.
            $attributes = [
                'page_type' => PageType::Static,
                'slug' => $seed['slug'],
                'title' => $seed['title'],
                'meta_description' => $seed['meta'],
                'date_published' => $now,
                'date_modified' => $now,
                'is_published' => true,
            ];
            if ($page === null || $page->body_blocks === null || $page->body_blocks === []) {
                $attributes['body_blocks'] = $seed['blocks'];
            }

            $this->pages->upsertPillarPage($generationKey, $seed['url_path'], $attributes);
            $count++;
        }

        return $count;
    }

    /**
     * @return list<array{url_path: string, slug: string, title: string, meta: string, blocks: list<array<string, mixed>>}>
     */
    private function seedPages(): array
    {
        return [
            [
                'url_path' => '/privacy/',
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
                'meta' => 'How NavyWeek.org handles data and privacy.',
                'blocks' => [
                    ['type' => 'paragraph', 'text' => 'NavyWeek.org is an independent editorial publisher. This policy explains what we collect and how we use it. Editors can update this content in the admin panel.'],
                    ['type' => 'heading', 'text' => 'What we collect'],
                    ['type' => 'paragraph', 'text' => 'We use privacy-respecting analytics to understand aggregate traffic. We do not sell personal information.'],
                ],
            ],
            [
                'url_path' => '/terms/',
                'slug' => 'terms',
                'title' => 'Terms of Use',
                'meta' => 'The terms governing use of NavyWeek.org.',
                'blocks' => [
                    ['type' => 'paragraph', 'text' => 'By using NavyWeek.org you agree to these terms. NavyWeek.org is not affiliated with the U.S. Navy, NAVCO, or any brand listed. Editors can update this content in the admin panel.'],
                    ['type' => 'heading', 'text' => 'Editorial independence'],
                    ['type' => 'paragraph', 'text' => 'Coverage is decided by editorial judgment and verifiable facts, never by affiliate arrangements.'],
                ],
            ],
            [
                'url_path' => '/contact/',
                'slug' => 'contact',
                'title' => 'Contact',
                'meta' => 'How to reach the NavyWeek.org editorial team.',
                'blocks' => [
                    ['type' => 'paragraph', 'text' => 'Questions or corrections? We welcome reader feedback, especially on discount accuracy. Editors can update this content in the admin panel.'],
                ],
            ],
            // The editorial-process page the TrustByline links from every guide
            // ("How we research & review"). Content ported from the live page.
            [
                'url_path' => '/our-process/',
                'slug' => 'our-process',
                'title' => 'How We Research and Verify Every Discount | NavyWeek.org',
                'meta' => 'How NavyWeek researches, verifies, and re-checks every military and veteran discount — the source order, the publish gate, and what we refuse to do.',
                'blocks' => [
                    ['type' => 'paragraph', 'text' => 'NavyWeek publishes one thing about military and veteran discounts: what we can prove. Every figure on this site traces back to a primary source we read ourselves — and dated.'],
                    ['type' => 'paragraph', 'text' => 'This page explains exactly how we do that, step by step, so you can judge our work — and so the people who rely on it know what stands behind it.'],
                    ['type' => 'paragraph', 'text' => "We treat this as a high-stakes subject. A wrong percentage sends a veteran to a counter expecting a discount that was never real. Accuracy isn't a constraint on what we do here — it is the work."],
                    ['type' => 'heading', 'text' => 'For any brand, the process runs the same order — every time'],
                    ['type' => 'paragraph', 'text' => 'Pick the brand and audience to cover, ranked by real search demand. Read the offer against primary sources, in a fixed order of authority. Weigh every legitimate way to save — not just the official discount. Run each page through a publish gate it must pass to ship. Ship only what survives the gate, with sources and dates shown. Re-verify every page on a schedule and after anything changes.'],
                    ['type' => 'heading', 'text' => 'How we choose what to cover'],
                    ['type' => 'paragraph', 'text' => "We don't cover brands at random, and we don't cover them because an affiliate program pays well — we have no such arrangements. We maintain a research queue ranked by search demand: the brands and questions the military community is actually asking about."],
                    ['type' => 'heading', 'text' => 'How we research an offer'],
                    ['type' => 'paragraph', 'text' => 'The governing rule is simple and absolute: we never state a discount amount, eligible group, code, stacking claim, or term we haven’t confirmed against a primary source. When a source doesn’t confirm it, it does not go on the page.'],
                    ['type' => 'heading', 'text' => 'Finding the genuinely best way to save'],
                    ['type' => 'paragraph', 'text' => 'A page that only answers "does this brand have a military discount?" leaves money on the table — the official discount is frequently not the best deal. For each brand we compare every legitimate path to savings and say which one actually wins.'],
                    ['type' => 'heading', 'text' => 'We work sources in a fixed order of authority'],
                    ['type' => 'paragraph', 'text' => "The brand's own discount, help, terms, checkout, or offer page is the top source for the percentage, codes, and fine print. Verification partners (ID.me, SheerID, GovX, WeSalute, VerifyPass) often state the exact percentage and eligible groups. Store locators and regional pages cover in-store rules and exclusions. News coverage is context only and is re-verified against the brand before publishing. Coupon aggregators are never treated as fact — we read them only to see what false claims are circulating so we can correct them."],
                    ['type' => 'paragraph', 'text' => 'The most authoritative source always wins. Anything below high confidence gets hedged in the copy — or left out entirely.'],
                    ['type' => 'heading', 'text' => 'A brand cannot be published unless we have all of this'],
                    ['type' => 'paragraph', 'text' => 'Research becomes a page only after it clears a gate. A documented "no discount exists" finding passes too — it meets the same standard of proof. We require a verified link to the brand\'s own discount page (https://, not an affiliate link, not a dead link, not a generic homepage); which provider gates the offer and how a customer proves eligibility; the actual value, or an explicit sourced statement that the brand publishes none; and the fine print — caps, excluded categories, one-code-per-order windows, regional limits, and no-stacking rules.'],
                    ['type' => 'heading', 'text' => 'What we will not do'],
                    ['type' => 'paragraph', 'text' => 'To make the standard concrete — we refuse to infer a discount from a coupon site, to state a percentage no primary source confirms, to present an affiliate link as the brand\'s official page, or to leave a verified-stale figure on the site because it still attracts traffic.'],
                ],
            ],
        ];
    }
}

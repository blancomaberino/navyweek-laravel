<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Pages;

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;

/**
 * Seeds the two YMYL guide content pages — `/va-disability/` and `/veterans-home-care/`
 * — as DB-driven pages (body in `body_blocks`). Their JSON-LD is Article + author Person
 * + reviewer Person + WebPage, and deliberately NO FAQPage (validate-jsonld
 * REQUIRED_TYPES). Per the DB/CMS decision the body is editor-managed: this seeds a
 * faithful initial body (the legacy intro migrated verbatim + a section overview + the
 * authoritative source pointers), which editors expand in Filament. For exact figures
 * (VA pay rates) the body points to the official VA rate table rather than transcribing
 * numbers — matching the legacy page, and the safe YMYL choice. Idempotent: never
 * clobbers an editor's body on re-run.
 */
final class GenerateYmylGuidePagesAction
{
    public function __construct(
        private readonly PageRepositoryInterface $pages,
    ) {}

    /**
     * @return int the number of guide pages seeded/refreshed
     */
    public function __invoke(): int
    {
        $count = 0;
        foreach ($this->guides() as $guide) {
            $generationKey = "content:{$guide['slug']}";
            // Identity by generation_key (not path) so an editor-renamed page is
            // recognized and its body is preserved on re-run; fall back to a path lookup
            // for a pre-generation_key (keyless) legacy row.
            $existing = $this->pages->findByGenerationKey($generationKey)
                ?? $this->pages->findPublishedByPath($guide['url_path']);
            $isNew = $existing === null || $existing->body_blocks === null || $existing->body_blocks === [];

            $attributes = [
                'page_type' => PageType::Static,
                'slug' => $guide['slug'],
                'title' => $guide['title'],
                'meta_description' => $guide['meta'],
                'og_image_path' => $guide['og'],
                'date_published' => $guide['date'],
                'date_modified' => $guide['date'],
                'is_published' => true,
            ];
            if ($isNew) {
                $attributes['body_blocks'] = $guide['blocks'];
            }

            $this->pages->upsertPillarPage($generationKey, $guide['url_path'], $attributes);
            $count++;
        }

        return $count;
    }

    /**
     * @return list<array{url_path: string, slug: string, title: string, meta: string, og: string, date: string, blocks: list<array<string, mixed>>}>
     */
    private function guides(): array
    {
        return [
            [
                'url_path' => '/va-disability/',
                'slug' => 'va-disability',
                'title' => 'VA Disability Benefits Guide (2026 Pay Rates, Ratings, How to File)',
                'meta' => 'An independent, plain-language guide to VA disability compensation — 2026 pay rates, how ratings work, how to file, and where to get free accredited help.',
                'og' => '/og/va-disability.png',
                'date' => '2026-01-15',
                'blocks' => [
                    ['type' => 'heading', 'text' => 'What VA disability compensation is'],
                    ['type' => 'paragraph', 'text' => 'VA disability is a monthly, tax-free benefit paid by the U.S. Department of Veterans Affairs to veterans with a service-connected disability (VA.gov — Eligibility). Federal income tax does not apply (IRS); most states also exempt it, but state treatment varies.'],
                    ['type' => 'paragraph', 'text' => 'It is distinct from a few other programs you may have heard of:'],
                    ['type' => 'list', 'items' => [
                        'VA pension is a separate, needs-based benefit for low-income wartime veterans (VA.gov — Pension).',
                        'SSDI is paid by the Social Security Administration for a disability that prevents substantial work, regardless of cause (SSA.gov). Many veterans receive VA disability and SSDI at the same time.',
                        'Military retirement is a separate DoD benefit for service members who meet the retirement criteria. Concurrent receipt rules (CRDP / CRSC) govern how retirement and VA disability interact for eligible retirees (DFAS — CRDP).',
                    ]],
                    ['type' => 'note', 'text' => 'For 2026 pay figures, confirm the current amount on the official VA rate table — the 2026 monthly rates took effect December 1, 2025 following a 2.5% cost-of-living adjustment: https://www.va.gov/disability/compensation-rates/veteran-rates/'],
                    ['type' => 'heading', 'text' => 'What this guide covers'],
                    ['type' => 'list', 'items' => [
                        'Who may qualify', '2026 VA disability pay rates', 'How VA ratings work', 'Combined ratings',
                        'Evidence the VA considers', 'How to file a claim', 'Intent to file & effective dates',
                        'Claim types', 'C&P exams', 'Dependents, SMC, Aid & Attendance, Housebound',
                        'TDIU (Total Disability Individual Unemployability)', 'PACT Act (toxic exposure)',
                        'Common mistakes to avoid', 'Where to get free & accredited help',
                    ]],
                    ['type' => 'heading', 'text' => 'Official VA resources'],
                    ['type' => 'list', 'items' => [
                        'VA.gov — Eligibility: https://www.va.gov/disability/eligibility/',
                        'VA.gov — 2026 Veteran Compensation Rates: https://www.va.gov/disability/compensation-rates/veteran-rates/',
                        'VA.gov — Pension: https://www.va.gov/pension/eligibility/',
                        'SSA.gov — Disability: https://www.ssa.gov/benefits/disability/',
                        'DFAS — CRDP: https://www.dfas.mil/RetiredMilitary/disability/crdp/',
                    ]],
                ],
            ],
            [
                'url_path' => '/veterans-home-care/',
                'slug' => 'veterans-home-care',
                'title' => "Veterans Home Care: A Family's Guide to VA Benefits, Eligibility, and Options | NavyWeek.org",
                'meta' => 'An independent guide to veterans home care — VA-arranged services vs. the Aid and Attendance pension, 2026 rates, who qualifies, and how to apply.',
                'og' => '/og/veterans-home-care.png',
                'date' => '2026-06-02',
                'blocks' => [
                    ['type' => 'heading', 'text' => 'The one thing most families get wrong'],
                    ['type' => 'paragraph', 'text' => "Here's the distinction that trips everyone up. The VA runs two separate branches that both touch home care, and they don't talk to each other the way you'd expect."],
                    ['type' => 'paragraph', 'text' => "The Veterans Health Administration (VHA) provides or arranges actual care. A nurse, an aide, or a medical team shows up at the house. You don't get cash; you get services."],
                    ['type' => 'paragraph', 'text' => 'The Veterans Benefits Administration (VBA) sends money. The main program here is Aid and Attendance, a tax-free monthly pension supplement you can spend on whatever care you choose, including hiring a private agency or, in many cases, a family member.'],
                    ['type' => 'note', 'text' => 'Two branches, two applications, two sets of rules. Some families qualify for both. The rest of this guide is organized around that split, so keep it in mind as you read.'],
                    ['type' => 'paragraph', 'text' => "One more practical note: the VA doesn't directly employ the aides who come to your home. It contracts that work out through its Community Care Network (CCN) — five regional networks managed by outside administrators (Optum handles Regions 1–3; TriWest handles Regions 4–5). That's why the agency at your door is a local company, not a VA employee."],
                    ['type' => 'heading', 'text' => 'What this guide covers'],
                    ['type' => 'list', 'items' => [
                        'VA home care programs (the services side)', 'Aid and Attendance: the program that pays you',
                        'Who qualifies for VA home care', 'How to apply', 'What it costs: VA-funded care vs. private pay',
                        'Can you pay a family member to provide care?', 'How to choose a veterans home care agency',
                    ]],
                    ['type' => 'heading', 'text' => 'Where to get help'],
                    ['type' => 'note', 'text' => 'For eligibility and to apply, start at the official VA geriatrics and long-term care site: https://www.va.gov/geriatrics/ — and for the Aid and Attendance pension supplement: https://www.va.gov/pension/aid-attendance-housebound/'],
                ],
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Pages;

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use Illuminate\Support\Carbon;

/**
 * Generates the `/discount/` directory landing page — the top-level discount hub
 * (page_type Static, no pageable). Title interpolates the live published-brand count,
 * meta/dates mirror the legacy DiscountHub, and the legacy HUB_FAQS are seeded onto
 * the page's polymorphic `faqs` (so the FAQPage stays data-driven). Idempotent.
 */
final class GenerateDiscountIndexPageAction
{
    private const HUB_PUBLISHED = '2026-06-10';

    private const META_DESCRIPTION = "Browse verified military and veteran discounts from major brands — who qualifies, how to verify with ID.me or GovX, how to redeem, and what's excluded.";

    /**
     * The legacy DiscountHub HUB_FAQS, ported verbatim (schema ↔ content parity).
     *
     * @var list<array{question: string, answer: string}>
     */
    private const HUB_FAQS = [
        [
            'question' => 'What military discounts can I get from major brands?',
            'answer' => 'Many national retailers offer 10–25% military and veteran discounts, usually verified for free through an identity service such as ID.me or GovX. This directory documents each program in plain language — who qualifies, how to verify, how to redeem, and the exclusions to watch for — and links to the brand’s official page so you can confirm the current terms before you buy.',
        ],
        [
            'question' => 'How do military discounts get verified online?',
            'answer' => 'Most online military discounts are verified at checkout through a third-party identity provider — ID.me is the most common, with GovX and SheerID also widely used. You create a free account once, confirm your service status, and the discount applies. The same verification is then reused across other partner brands.',
        ],
        [
            'question' => 'Do veterans qualify for these discounts, or only active duty?',
            'answer' => 'It depends on the brand, but most programs covered here extend to veterans and retirees in addition to active-duty, reserve, and National Guard members. Many also include military spouses and dependents, and parallel discounts for first responders, medical workers, teachers, and government employees. Each guide notes exactly who is eligible.',
        ],
        [
            'question' => 'Is NavyWeek.org affiliated with these brands?',
            'answer' => 'No. NavyWeek.org is an independent guide. We are not affiliated with, endorsed by, or sponsored by any company listed here, and the brands set and control their own discount terms. Company names and logos are trademarks of their respective owners, shown for identification only. Always confirm the current offer on the official page.',
        ],
    ];

    public function __construct(
        private readonly PageRepositoryInterface $pages,
    ) {}

    /**
     * @return int always 1 (the single /discount/ index page)
     */
    public function __invoke(): int
    {
        // The query filters to Offer-backed discount-brand pages, so this count equals
        // the schema's ItemList numberOfItems (which skips any non-Offer pageable).
        $count = $this->pages->allPublishedDiscountBrandPages()->count();
        $title = 'Military & Veteran Discounts Directory'
            .($count > 1 ? " — {$count}+ Brands" : '')
            .' | NavyWeek.org';

        $page = $this->pages->upsertPillarPage('/discount/', [
            'page_type' => PageType::Static,
            'slug' => 'discount',
            'title' => $title,
            'meta_description' => self::META_DESCRIPTION,
            'og_image_path' => '/og/discount/hub.png',
            'date_published' => Carbon::parse(self::HUB_PUBLISHED),
            'date_modified' => Carbon::parse(self::HUB_PUBLISHED),
            'is_published' => true,
        ]);

        $this->seedHubFaqs($page);

        return 1;
    }

    /** Idempotently (re)seed the page's FAQs from the ported HUB_FAQS constant. */
    private function seedHubFaqs(Page $page): void
    {
        $page->replaceFaqs(array_map(static fn (array $faq, int $i): array => [
            'question' => $faq['question'],
            'answer' => $faq['answer'],
            'sort_order' => $i + 1,
        ], self::HUB_FAQS, array_keys(self::HUB_FAQS)));
    }
}

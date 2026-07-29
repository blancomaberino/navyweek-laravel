<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\OfferType;
use App\Domain\Catalog\Enums\RedemptionChannel;
use App\Domain\Catalog\Models\Offer;
use App\Domain\Crm\Models\Connection;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

/**
 * Build a full discount-brand page: connection → primary offer (+ tiers, steps,
 * FAQs, sources) → page pointing its pageable at that offer.
 */
function discountPage(): Page
{
    $connection = Connection::factory()->create(['brand' => 'YETI', 'slug' => 'yeti']);

    $offer = $connection->offers()->create([
        'offer_type' => OfferType::Everyday,
        'internal_label' => 'YETI — Everyday',
        'headline_discount' => 'Up to 20% off for the military community',
        'discount_summary' => 'YETI verifies via ID.me.',
        'audience_label' => 'Military & Veteran',
        'eligibility' => ['Active duty', 'Veterans'],
        'exclusions' => ['Not stackable with sales'],
        'key_facts' => ['Verified through ID.me'],
        'official_url' => 'https://www.yeti.com/id-me-deals',
        'cta_label' => 'Shop YETI with ID.me',
        'is_primary' => true,
        'is_published' => true,
    ]);
    $offer->tiers()->create(['audience' => 'Active duty', 'amount' => '20% off', 'sort_order' => 0]);
    $offer->redemptionSteps()->create(['channel' => RedemptionChannel::Online, 'title' => 'Verify with ID.me', 'detail' => 'Click the badge.', 'sort_order' => 0]);
    $offer->redemptionSteps()->create(['channel' => RedemptionChannel::InStore, 'title' => 'Show your ID', 'detail' => 'Present your military ID.', 'sort_order' => 0]);
    $offer->faqs()->create(['question' => 'Who qualifies?', 'answer' => 'Active duty and veterans.', 'sort_order' => 0]);
    $offer->sources()->create(['label' => 'YETI ID.me page', 'url' => 'https://www.yeti.com/id-me-deals', 'publisher' => 'YETI', 'sort_order' => 0]);

    $page = Page::create([
        'page_type' => PageType::DiscountBrand,
        'slug' => 'yeti-military-veteran',
        'url_path' => '/discount/yeti-military-veteran/',
        'canonical_path' => '/discount/yeti-military-veteran/',
        'title' => 'YETI Military & Veteran Discount 2026',
        'meta_description' => 'How the military community saves at YETI.',
        'og_type' => 'article',
        'og_image_path' => '/og/yeti.png',
        'date_published' => '2026-06-10',
        'date_modified' => '2026-07-20',
        'is_published' => true,
        'noindex' => false,
    ]);
    $page->pageable()->associate($offer)->save();

    return $page;
}

function fetch(string $path): TestResponse
{
    return TestResponse::fromBaseResponse(
        app(Kernel::class)->handle(Request::create("http://localhost{$path}"))
    );
}

it('renders the discount guide body from the primary offer', function () {
    discountPage();

    $res = fetch('/discount/yeti-military-veteran/')->assertOk();

    $res->assertSee('YETI Military &amp; Veteran Discount 2026', false) // h1 (Blade-escaped &)
        ->assertSee('Up to 20% off for the military community')
        ->assertSee('not affiliated', false) // independence disclosure
        ->assertSee('Shop YETI with ID.me')
        ->assertSee('Savings by audience')
        ->assertSee('Who is eligible')
        ->assertSee('How to redeem')
        ->assertSee('Verify with ID.me')
        ->assertSee('Show your ID')
        ->assertSee('Who qualifies?')
        ->assertSee('YETI ID.me page')
        // the hero CTA is a monetized, sponsored outbound link
        ->assertSee('rel="sponsored noopener noreferrer"', false);
});

it('emits the discount JSON-LD graph: Organization + Article + Person + FAQPage', function () {
    discountPage();

    $res = fetch('/discount/yeti-military-veteran/')->assertOk();

    $res->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"Article"', false)
        ->assertSee('"headline":"YETI Military & Veteran Discount (2026)"', false)
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"@type":"WebSite"', false)
        // author + reviewer Person nodes
        ->assertSee('"@id":"https://www.navyweek.org/authors/t-alford/#person"', false)
        ->assertSee('"@id":"https://www.navyweek.org/discount/yeti-military-veteran/#reviewer"', false)
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('"name":"Who qualifies?"', false)
        // Article dates come from the page's build-clock columns
        ->assertSee('"datePublished":"2026-06-10"', false)
        ->assertSee('"dateModified":"2026-07-20"', false);
});

it('still renders the minimal shell for a non-discount page type', function () {
    Page::create([
        'page_type' => PageType::Static,
        'slug' => 'about',
        'url_path' => '/about/',
        'title' => 'About NavyWeek',
        'is_published' => true,
        'noindex' => false,
    ]);

    fetch('/about/')
        ->assertOk()
        ->assertSee('About NavyWeek')
        ->assertDontSee('independence-disclosure', false); // discount-only body class
});

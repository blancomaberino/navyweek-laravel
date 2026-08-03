<?php

declare(strict_types=1);

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Pages\GenerateHomePageAction;

it('seeds the home page row at / with the derived title, meta, and og image', function () {
    app(GenerateHomePageAction::class)();

    $page = Page::query()->where('url_path', '/')->firstOrFail();

    expect($page->page_type)->toBe(PageType::Home)
        ->and($page->slug)->toBe('home')
        ->and($page->generation_key)->toBe('content:home')
        ->and($page->is_published)->toBeTrue()
        ->and($page->og_image_path)->toBe('/og/home.png')
        ->and($page->title)->toBe('Navy Week 2026 — Free Events in 12 Cities Nationwide | NavyWeek.org')
        ->and($page->meta_description)->toContain('U.S. Navy Week 2026 brings sailors')
        // A data-driven hub: no stored body — the schedule renders from the pillar.
        ->and($page->body_blocks)->toBeNull();
});

it('seeds the home FAQs on the page', function () {
    app(GenerateHomePageAction::class)();

    $page = Page::query()->where('url_path', '/')->firstOrFail();

    expect($page->faqs()->count())->toBe(8)
        ->and($page->faqs()->orderBy('sort_order')->first()->question)->toBe('What is Navy Week?');
});

it('is idempotent — re-running keeps one home page and one FAQ set', function () {
    app(GenerateHomePageAction::class)();
    app(GenerateHomePageAction::class)();

    expect(Page::query()->where('page_type', PageType::Home)->count())->toBe(1);

    $page = Page::query()->where('url_path', '/')->firstOrFail();
    expect($page->faqs()->count())->toBe(8);
});

it('does not clobber editor-edited FAQs on re-run', function () {
    app(GenerateHomePageAction::class)();
    $page = Page::query()->where('url_path', '/')->firstOrFail();
    $page->replaceFaqs([['question' => 'Editor Q?', 'answer' => 'Editor A.', 'sort_order' => 0]]);

    app(GenerateHomePageAction::class)(); // re-run

    $page->refresh();
    expect($page->faqs()->count())->toBe(1)
        ->and($page->faqs()->first()->question)->toBe('Editor Q?');
});

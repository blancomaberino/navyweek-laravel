<?php

declare(strict_types=1);

use App\Domain\Publishing\Models\Page;
use Illuminate\Support\Facades\Artisan;

it('fills the trust columns on a discount page and leaves editor values alone', function () {
    $page = discountPage();                       // helper from DiscountGuideRenderTest
    $page->update(['trust_page_label' => 'Editor wording']);

    Artisan::call('backfill:discount-trust');

    $page->refresh();
    expect($page->h1)->toBe('YETI Military & Veteran Discount')
        ->and($page->editorial_source_priority)->not->toBeNull()
        ->and($page->editorial_review_cadence)->not->toBeNull()
        // An editor-supplied value is never clobbered by the backfill.
        ->and($page->trust_page_label)->toBe('Editor wording');
});

it('overwrites an existing value only with --force', function () {
    $page = discountPage();
    $page->update(['h1' => 'Stale h1']);

    Artisan::call('backfill:discount-trust');
    expect($page->refresh()->h1)->toBe('Stale h1');

    Artisan::call('backfill:discount-trust', ['--force' => true]);
    expect($page->refresh()->h1)->toBe('YETI Military & Veteran Discount');
});

it('is idempotent', function () {
    discountPage();

    Artisan::call('backfill:discount-trust');
    Artisan::call('backfill:discount-trust');

    expect(Page::query()->whereNotNull('h1')->count())->toBe(1);
});

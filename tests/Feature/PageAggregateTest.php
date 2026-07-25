<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Offer;
use App\Domain\Crm\Models\Connection;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use Illuminate\Support\Carbon;

it('casts the SEO/JSON-LD columns', function () {
    $page = Page::factory()->create([
        'noindex' => true,
        'json_ld' => [['@type' => 'FAQPage']],
        'date_published' => '2026-07-25T09:00:00-04:00',
        'date_modified' => '2026-07-25T12:30:00-04:00',
    ]);

    $fresh = $page->fresh();

    expect($fresh->noindex)->toBeTrue()
        ->and($fresh->json_ld)->toBe([['@type' => 'FAQPage']])
        ->and($fresh->date_published)->toBeInstanceOf(Carbon::class)
        ->and($fresh->date_modified)->toBeInstanceOf(Carbon::class)
        ->and($fresh->page_type)->toBe(PageType::Static);
});

it('defaults og_type to website and noindex to false', function () {
    // Insert with only the routing-critical columns, as slice-1 rows do.
    $page = Page::create([
        'page_type' => PageType::DiscountBrand,
        'slug' => 'nike',
        'url_path' => '/discount/nike/',
        'is_published' => true,
    ]);

    $fresh = $page->fresh();

    expect($fresh->og_type)->toBe('website')
        ->and($fresh->noindex)->toBeFalse()
        ->and($fresh->title)->toBeNull()
        ->and($fresh->json_ld)->toBeNull()
        ->and($fresh->pageable)->toBeNull();
});

it('resolves its pageable to a connection', function () {
    $connection = Connection::factory()->create();
    $page = Page::factory()->create([
        'page_type' => PageType::DiscountBrand,
        'pageable_type' => $connection->getMorphClass(),
        'pageable_id' => $connection->id,
    ]);

    $pageable = $page->fresh()->pageable;

    expect($pageable)->toBeInstanceOf(Connection::class)
        ->and($pageable->is($connection))->toBeTrue();
});

it('resolves its pageable to an offer (polymorphic across aggregates)', function () {
    $offer = Offer::factory()->create();
    $page = Page::factory()->create([
        'pageable_type' => $offer->getMorphClass(),
        'pageable_id' => $offer->id,
    ]);

    $pageable = $page->fresh()->pageable;

    expect($pageable)->toBeInstanceOf(Offer::class)
        ->and($pageable->is($offer))->toBeTrue();
});

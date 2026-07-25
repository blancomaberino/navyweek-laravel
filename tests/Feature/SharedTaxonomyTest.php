<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Offer;
use App\Domain\Crm\Enums\Audience as AudienceEnum;
use App\Domain\Crm\Models\Audience;
use App\Domain\Publishing\Models\Page;
use App\Domain\Research\Models\Research;
use App\Domain\Shared\Enums\ConfidenceLevel;
use App\Domain\Shared\Enums\SourceType;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;
use Database\Seeders\AudienceSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('links an offer to the audiences it serves and back', function () {
    $offer = Offer::factory()->create();
    $military = Audience::factory()->ofKey(AudienceEnum::Military)->create();
    $veteran = Audience::factory()->ofKey(AudienceEnum::Veteran)->create();

    $offer->audiences()->attach([$military->id, $veteran->id]);

    expect($offer->audiences)->toHaveCount(2)
        ->and($military->offers()->whereKey($offer->id)->exists())->toBeTrue()
        ->and($offer->audiences->first()->key)->toBeInstanceOf(AudienceEnum::class);
});

it('casts an audience key to the enum with its label', function () {
    $audience = Audience::factory()->ofKey(AudienceEnum::Student)->create();

    expect($audience->fresh()->key)->toBe(AudienceEnum::Student)
        ->and($audience->label)->toBe('Student');
});

it('rejects a duplicate offer-audience pairing', function () {
    $offer = Offer::factory()->create();
    $military = Audience::factory()->ofKey(AudienceEnum::Military)->create();

    $offer->audiences()->attach($military->id);

    expect(fn () => $offer->audiences()->attach($military->id))
        ->toThrow(QueryException::class);
});

it('cascades the pivot rows when an offer is deleted', function () {
    $offer = Offer::factory()->create();
    $offer->audiences()->attach(Audience::factory()->create()->id);

    $offer->delete();

    expect(DB::table('offer_audience')->count())->toBe(0);
});

it('attaches sources polymorphically to an offer, research and page', function () {
    $offer = Offer::factory()->create();
    $research = Research::factory()->create();
    $page = Page::factory()->create();

    Source::factory()->for($offer, 'sourceable')->create();
    Source::factory()->for($research, 'sourceable')->create();
    Source::factory()->for($page, 'sourceable')->create();

    expect($offer->sources)->toHaveCount(1)
        ->and($research->sources)->toHaveCount(1)
        ->and($page->sources)->toHaveCount(1)
        ->and($offer->sources->first()->sourceable->is($offer))->toBeTrue()
        ->and($offer->sources->first()->source_type)->toBe(SourceType::Official)
        ->and($offer->sources->first()->confidence)->toBe(ConfidenceLevel::High);
});

it('orders sources by sort_order', function () {
    $offer = Offer::factory()->create();
    Source::factory()->for($offer, 'sourceable')->create(['label' => 'second', 'sort_order' => 2]);
    Source::factory()->for($offer, 'sourceable')->create(['label' => 'first', 'sort_order' => 1]);

    expect($offer->sources->pluck('label')->all())->toBe(['first', 'second']);
});

it('attaches faqs polymorphically to a page and an offer, in order', function () {
    $page = Page::factory()->create();
    $offer = Offer::factory()->create();

    Faq::factory()->for($page, 'faqable')->create(['question' => 'B', 'sort_order' => 2]);
    Faq::factory()->for($page, 'faqable')->create(['question' => 'A', 'sort_order' => 1]);
    Faq::factory()->for($offer, 'faqable')->create();

    expect($page->faqs->pluck('question')->all())->toBe(['A', 'B'])
        ->and($offer->faqs)->toHaveCount(1)
        ->and($page->faqs->first()->faqable->is($page))->toBeTrue();
});

it('seeds one audience row per enum case, idempotently', function () {
    (new AudienceSeeder)->run();
    (new AudienceSeeder)->run();

    expect(Audience::count())->toBe(count(AudienceEnum::cases()))
        ->and(Audience::where('key', AudienceEnum::Healthcare->value)->exists())->toBeTrue();
});

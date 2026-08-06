<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\OfferType;
use App\Domain\Catalog\Models\Offer;
use App\Domain\Crm\Enums\Audience as AudienceEnum;
use App\Domain\Crm\Models\Audience;
use App\Domain\Crm\Models\Connection;
use App\Filament\Resources\Offers\Pages\EditOffer;
use App\Filament\Resources\Offers\Pages\ListOffers;
use App\Filament\Resources\Offers\RelationManagers\TiersRelationManager;
use App\Models\User;
use Database\Seeders\AudienceSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

/** Create an offer under a fresh connection. */
function makeOffer(array $attributes = []): Offer
{
    $connection = Connection::factory()->create();

    return $connection->offers()->create(array_merge([
        'offer_type' => OfferType::Everyday,
        'internal_label' => $connection->brand.' — Everyday',
        'headline_discount' => '20% off',
        'is_primary' => true,
        'is_published' => true,
    ], $attributes));
}

it('lists offers with the brand and offer-type columns', function () {
    $everyday = makeOffer();
    $advisory = makeOffer(['offer_type' => OfferType::AdvisoryNoDiscount, 'is_primary' => false]);

    Livewire::test(ListOffers::class)
        ->assertCanSeeTableRecords([$everyday, $advisory])
        ->assertCanRenderTableColumn('connection.brand')
        ->assertCanRenderTableColumn('offer_type');
});

it('filters offers by type', function () {
    $everyday = makeOffer();
    $advisory = makeOffer(['offer_type' => OfferType::AdvisoryNoDiscount]);

    Livewire::test(ListOffers::class)
        ->filterTable('offer_type', OfferType::AdvisoryNoDiscount->value)
        ->assertCanSeeTableRecords([$advisory])
        ->assertCanNotSeeTableRecords([$everyday]);
});

it('edits an offer and persists the change', function () {
    $offer = makeOffer(['headline_discount' => 'Old']);

    Livewire::test(EditOffer::class, ['record' => $offer->getRouteKey()])
        ->fillForm(['headline_discount' => '25% off for veterans'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($offer->refresh()->headline_discount)->toBe('25% off for veterans');
});

/**
 * The edit page 500s whenever the `audiences` table has ANY row: `preload()` makes
 * Filament load and label every audience as an option, independent of what this offer
 * has attached. So every one of the 981 live offers was unreachable, not just the ones
 * with audiences.
 *
 * The plain edit test above never caught it because RefreshDatabase leaves `audiences`
 * empty and no seeder runs in the Feature suite — there were zero options to label. The
 * load-bearing line in these tests is therefore the seed(), not the attach().
 */
it('renders the edit form for an offer that has audiences attached', function () {
    $this->seed(AudienceSeeder::class);

    $offer = makeOffer();
    $military = Audience::query()->where('key', AudienceEnum::Military)->sole();
    $veteran = Audience::query()->where('key', AudienceEnum::Veteran)->sole();
    $offer->audiences()->attach([$military->id, $veteran->id]);

    Livewire::test(EditOffer::class, ['record' => $offer->getRouteKey()])
        ->assertSuccessful()
        // The SELECTED values round-trip. `assertSee` alone would not prove this: the
        // preloaded option list contains every audience whether attached or not.
        ->assertFormSet(['audiences' => [$military->id, $veteran->id]])
        // …and they are labelled by the enum's name, not the storage key.
        ->assertSee(AudienceEnum::Military->label());
});

it('renders the edit form for an offer with no audiences attached', function () {
    // The other half of the real failure: an empty pivot still 500'd, because the
    // option list is loaded from the whole table.
    $this->seed(AudienceSeeder::class);

    Livewire::test(EditOffer::class, ['record' => makeOffer()->getRouteKey()])
        ->assertSuccessful();
});

it('persists an audience change through the pivot', function () {
    $this->seed(AudienceSeeder::class);

    $offer = makeOffer();
    $military = Audience::query()->where('key', AudienceEnum::Military)->sole();
    $veteran = Audience::query()->where('key', AudienceEnum::Veteran)->sole();
    $offer->audiences()->attach($military->id);

    Livewire::test(EditOffer::class, ['record' => $offer->getRouteKey()])
        ->fillForm(['audiences' => [$veteran->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($offer->refresh()->audiences->pluck('id')->all())->toBe([$veteran->id]);
});

it('manages savings tiers through the relation manager', function () {
    $offer = makeOffer();
    $offer->tiers()->create(['audience' => 'Active duty', 'amount' => '20% off', 'sort_order' => 0]);

    Livewire::test(TiersRelationManager::class, [
        'ownerRecord' => $offer,
        'pageClass' => EditOffer::class,
    ])->assertCanSeeTableRecords($offer->tiers()->get());
});

<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\OfferType;
use App\Domain\Crm\Models\Connection;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Research\Actions\MarkResearchVerifiedAction;
use App\Domain\Research\Enums\ResearchStatus;
use App\Domain\Research\Exceptions\CannotVerifyNonLatestResearchException;
use App\Domain\Research\Models\Research;
use App\Filament\Resources\Research\Pages\ListResearch;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('marks the brief verified and recomputes the connection review cadence', function () {
    $connection = Connection::factory()->create([
        'research_cadence_days' => 30,
        'last_verified_at' => null,
        'next_review_due' => null,
    ]);
    $research = Research::factory()->create([
        'connection_id' => $connection->id,
        'status' => ResearchStatus::Draft,
        'last_verified' => null,
    ]);

    app(MarkResearchVerifiedAction::class)($research);

    $research->refresh();
    $connection->refresh();

    expect($research->status)->toBe(ResearchStatus::Complete)
        ->and($research->last_verified?->toDateString())->toBe(now()->toDateString())
        ->and($connection->last_verified_at?->toDateString())->toBe(now()->toDateString())
        ->and($connection->next_review_due?->toDateString())->toBe(now()->addDays(30)->toDateString());
});

it('refuses to verify a superseded (non-latest) brief and writes nothing', function () {
    $connection = Connection::factory()->create(['last_verified_at' => null, 'next_review_due' => null]);
    $old = Research::factory()->create([
        'connection_id' => $connection->id,
        'version' => 1,
        'status' => ResearchStatus::Superseded,
        'last_verified' => null,
    ]);
    // A newer version is the current source of truth.
    Research::factory()->create([
        'connection_id' => $connection->id,
        'version' => 2,
        'status' => ResearchStatus::Complete,
    ]);

    expect(fn () => app(MarkResearchVerifiedAction::class)($old))
        ->toThrow(CannotVerifyNonLatestResearchException::class);

    // The transaction rolled back / never wrote: the old brief and the connection
    // cadence are untouched.
    expect($old->refresh()->status)->toBe(ResearchStatus::Superseded)
        ->and($old->last_verified)->toBeNull()
        ->and($connection->refresh()->last_verified_at)->toBeNull()
        ->and($connection->next_review_due)->toBeNull();
});

it('does not touch page build-clock dates when verifying research', function () {
    $connection = Connection::factory()->create();
    $research = Research::factory()->create(['connection_id' => $connection->id]);
    $offer = $connection->offers()->create(['offer_type' => OfferType::Everyday, 'is_primary' => true, 'is_published' => true]);
    $page = Page::create([
        'page_type' => PageType::DiscountBrand,
        'slug' => 'brand-military-veteran',
        'url_path' => '/discount/brand-military-veteran/',
        'title' => 'Brand Discount',
        'is_published' => true,
        'date_published' => '2026-06-10',
        'date_modified' => '2026-07-20',
    ]);
    $page->pageable()->associate($offer)->save();

    app(MarkResearchVerifiedAction::class)($research);

    $page->refresh();
    expect($page->date_published?->toDateString())->toBe('2026-06-10')
        ->and($page->date_modified?->toDateString())->toBe('2026-07-20');
});

it('exposes a Mark verified action on the research table that runs the recompute', function () {
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $connection = Connection::factory()->create(['research_cadence_days' => 45]);
    $research = Research::factory()->create([
        'connection_id' => $connection->id,
        'status' => ResearchStatus::Stale,
    ]);

    Livewire::test(ListResearch::class)
        ->callTableAction('markVerified', $research)
        ->assertHasNoTableActionErrors();

    expect($research->refresh()->status)->toBe(ResearchStatus::Complete)
        ->and($connection->refresh()->next_review_due?->toDateString())->toBe(now()->addDays(45)->toDateString());
});

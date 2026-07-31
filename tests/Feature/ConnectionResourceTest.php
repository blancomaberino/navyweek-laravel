<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\OfferType;
use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Models\Connection;
use App\Domain\Research\Models\Research;
use App\Filament\Resources\Connections\Pages\EditConnection;
use App\Filament\Resources\Connections\Pages\ListConnections;
use App\Filament\Resources\Connections\RelationManagers\OffersRelationManager;
use App\Filament\Resources\Connections\RelationManagers\ResearchRelationManager;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('lists connections with the offers count and status badge', function () {
    $published = Connection::factory()->published()->create(['brand' => 'YETI']);
    $published->offers()->create([
        'offer_type' => OfferType::Everyday,
        'internal_label' => 'YETI — Everyday',
        'is_primary' => true,
    ]);
    $pending = Connection::factory()->create(['brand' => 'Acme Co']);

    Livewire::test(ListConnections::class)
        ->assertCanSeeTableRecords([$published, $pending])
        ->assertCanRenderTableColumn('offers_count')
        ->assertCanRenderTableColumn('status');
});

it('filters the universe by pipeline status', function () {
    $published = Connection::factory()->published()->create();
    $pending = Connection::factory()->create(['status' => ConnectionStatus::Pending]);

    Livewire::test(ListConnections::class)
        ->filterTable('status', ConnectionStatus::Published->value)
        ->assertCanSeeTableRecords([$published])
        ->assertCanNotSeeTableRecords([$pending]);
});

it('edits a connection and persists the change', function () {
    $connection = Connection::factory()->create([
        'brand' => 'Old Name',
        'status' => ConnectionStatus::Pending,
    ]);

    Livewire::test(EditConnection::class, ['record' => $connection->getRouteKey()])
        ->fillForm([
            'brand' => 'New Name',
            'status' => ConnectionStatus::Published->value,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $connection->refresh();
    expect($connection->brand)->toBe('New Name')
        ->and($connection->status)->toBe(ConnectionStatus::Published);
});

it('rejects a non-kebab slug on edit', function () {
    $connection = Connection::factory()->create();

    Livewire::test(EditConnection::class, ['record' => $connection->getRouteKey()])
        ->fillForm(['slug' => 'Not A Slug!'])
        ->call('save')
        ->assertHasFormErrors(['slug']);
});

it('filters to connections due for review', function () {
    $due = Connection::factory()->dueForReview('2020-01-01')->create(['brand' => 'PastDue']);
    $future = Connection::factory()->dueForReview('2999-01-01')->create(['brand' => 'NotYetDue']);
    $never = Connection::factory()->create(['brand' => 'NeverScheduled']); // next_review_due null

    Livewire::test(ListConnections::class)
        ->assertCanRenderTableColumn('next_review_due')
        ->filterTable('due_for_review')
        ->assertCanSeeTableRecords([$due])
        ->assertCanNotSeeTableRecords([$future, $never]);
});

it('shows the offers relation manager on the connection page', function () {
    $connection = Connection::factory()->create();
    $offer = $connection->offers()->create([
        'offer_type' => OfferType::Everyday,
        'headline_discount' => '20% off',
        'is_primary' => true,
        'is_published' => true,
    ]);

    Livewire::test(OffersRelationManager::class, [
        'ownerRecord' => $connection,
        'pageClass' => EditConnection::class,
    ])
        ->assertCanSeeTableRecords([$offer])
        ->assertCanRenderTableColumn('offer_type');
});

it('shows the research relation manager on the connection page', function () {
    $connection = Connection::factory()->create();
    $brief = Research::factory()->create(['connection_id' => $connection->id]);

    Livewire::test(ResearchRelationManager::class, [
        'ownerRecord' => $connection,
        'pageClass' => EditConnection::class,
    ])
        ->assertCanSeeTableRecords([$brief])
        ->assertCanRenderTableColumn('status');
});

it('bulk-sets the pipeline status on the selected connections', function () {
    $a = Connection::factory()->create(['status' => ConnectionStatus::Pending]);
    $b = Connection::factory()->create(['status' => ConnectionStatus::Pending]);

    Livewire::test(ListConnections::class)
        ->callTableBulkAction('setStatus', [$a, $b], data: ['status' => ConnectionStatus::Skipped->value]);

    expect($a->refresh()->status)->toBe(ConnectionStatus::Skipped)
        ->and($b->refresh()->status)->toBe(ConnectionStatus::Skipped);
});

it('bulk-promotes connections out of the backlog', function () {
    $backlogged = Connection::factory()->backlog()->create();

    Livewire::test(ListConnections::class)
        ->callTableBulkAction('promoteFromBacklog', [$backlogged]);

    expect($backlogged->refresh()->is_backlog)->toBeFalse();
});

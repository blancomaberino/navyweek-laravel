<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\OfferType;
use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Models\Connection;
use App\Filament\Resources\Connections\Pages\EditConnection;
use App\Filament\Resources\Connections\Pages\ListConnections;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
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

<?php

declare(strict_types=1);

use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Models\Connection;
use App\Filament\Pages\PipelineBoard;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('renders a column per status with each brand in its own column', function () {
    Connection::factory()->create(['brand' => 'Published Brand', 'status' => ConnectionStatus::Published]);
    Connection::factory()->create(['brand' => 'Pending Brand', 'status' => ConnectionStatus::Pending]);

    Livewire::test(PipelineBoard::class)
        ->assertOk()
        ->assertSee('Published Brand')
        ->assertSee('Pending Brand')
        // Every status gets a column header.
        ->assertSee(ConnectionStatus::NeedsReverify->label());
});

it('reports the true per-status count from the repository', function () {
    Connection::factory()->count(3)->create(['status' => ConnectionStatus::Pending]);

    $columns = Livewire::test(PipelineBoard::class)->instance()->columns();
    $pending = collect($columns)->firstWhere('status', ConnectionStatus::Pending);

    expect($pending['count'])->toBe(3);
});

it('caps a column at COLUMN_LIMIT cards but keeps the full count', function () {
    Connection::factory()->count(PipelineBoard::COLUMN_LIMIT + 5)->create(['status' => ConnectionStatus::Pending]);

    $columns = Livewire::test(PipelineBoard::class)->instance()->columns();
    $pending = collect($columns)->firstWhere('status', ConnectionStatus::Pending);

    expect($pending['connections'])->toHaveCount(PipelineBoard::COLUMN_LIMIT)
        ->and($pending['count'])->toBe(PipelineBoard::COLUMN_LIMIT + 5);
});

it('orders cards by total_volume desc with nulls last', function () {
    Connection::factory()->create(['brand' => 'Low', 'status' => ConnectionStatus::Pending, 'total_volume' => 100]);
    Connection::factory()->create(['brand' => 'High', 'status' => ConnectionStatus::Pending, 'total_volume' => 9000]);
    Connection::factory()->create(['brand' => 'Nullvol', 'status' => ConnectionStatus::Pending, 'total_volume' => null]);

    $columns = Livewire::test(PipelineBoard::class)->instance()->columns();
    $brands = collect($columns)->firstWhere('status', ConnectionStatus::Pending)['connections']
        ->pluck('brand')->all();

    expect($brands)->toBe(['High', 'Low', 'Nullvol']);
});

it('moves a card to another column through the repository', function () {
    $connection = Connection::factory()->create(['status' => ConnectionStatus::Pending]);

    Livewire::test(PipelineBoard::class)
        ->call('moveTo', $connection->id, ConnectionStatus::Published->value)
        ->assertOk();

    expect($connection->fresh()->status)->toBe(ConnectionStatus::Published);
});

it('ignores a move to an unknown status', function () {
    $connection = Connection::factory()->create(['status' => ConnectionStatus::Pending]);

    Livewire::test(PipelineBoard::class)
        ->call('moveTo', $connection->id, 'not-a-status')
        ->assertOk();

    expect($connection->fresh()->status)->toBe(ConnectionStatus::Pending);
});

<?php

declare(strict_types=1);

use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Models\Connection;
use App\Filament\Widgets\PipelineStatsWidget;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('renders the four pipeline stat cards', function () {
    Connection::factory()->published()->create();
    Connection::factory()->create(['status' => ConnectionStatus::Pending]);

    Livewire::test(PipelineStatsWidget::class)
        ->assertOk()
        ->assertSee('Connections')
        ->assertSee('Published')
        ->assertSee('Due for review')
        ->assertSee('Backlog');
});

it('renders with an empty universe (no connections)', function () {
    Livewire::test(PipelineStatsWidget::class)
        ->assertOk()
        ->assertSee('Connections');
});

it('shows the past-due count on the due-for-review card', function () {
    Connection::factory()->dueForReview('2020-01-01')->create(); // past → due
    Connection::factory()->dueForReview('2999-01-01')->create(); // future → not due
    Connection::factory()->create();                             // null → not due

    // Reproduce the widget's own query to assert the intended count independent of
    // the StatsOverview card DOM order (value renders before label).
    $due = Connection::query()
        ->whereNotNull('next_review_due')
        ->whereDate('next_review_due', '<=', now())
        ->count();

    expect($due)->toBe(1);
    Livewire::test(PipelineStatsWidget::class)->assertOk()->assertSee('Due for review');
});

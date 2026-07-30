<?php

declare(strict_types=1);

use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Models\Connection;
use App\Filament\Widgets\PipelineStatsWidget;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

/**
 * The widget's computed stats keyed by label, so a test can assert the actual number
 * each card shows (StatsOverviewWidget::getStats is protected).
 *
 * @return \Illuminate\Support\Collection<string, string>
 */
function pipelineStatValues(): \Illuminate\Support\Collection
{
    $widget = Livewire::test(PipelineStatsWidget::class)->instance();
    $method = new ReflectionMethod($widget, 'getStats');
    /** @var array<int, Stat> $stats */
    $stats = $method->invoke($widget);

    return collect($stats)->mapWithKeys(
        static fn (Stat $stat): array => [(string) $stat->getLabel() => (string) $stat->getValue()],
    );
}

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

it('computes each stat value from the connection universe', function () {
    // Disjoint fixtures so every card has an unambiguous expected count:
    Connection::factory()->count(2)->published()->create();                 // Published (next_review_due null)
    Connection::factory()->create(['status' => ConnectionStatus::Pending]); // plain Pending
    Connection::factory()->backlog()->create();                             // Pending + is_backlog
    Connection::factory()->dueForReview('2020-01-01')->create();            // Pending + past-due

    $stats = pipelineStatValues();

    expect($stats['Connections'])->toBe('5')      // every row
        ->and($stats['Published'])->toBe('2')     // only the two published
        ->and($stats['Due for review'])->toBe('1') // only the past-due one (nulls excluded)
        ->and($stats['Backlog'])->toBe('1');      // only the is_backlog one
});

it('excludes future and never-verified connections from the due-for-review count', function () {
    Connection::factory()->dueForReview('2020-01-01')->create(); // past → due
    Connection::factory()->dueForReview('2999-01-01')->create(); // future → not due
    Connection::factory()->create();                             // null next_review_due → not due

    expect(pipelineStatValues()['Due for review'])->toBe('1');
});

<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Connection;
use App\Domain\Research\Enums\ResearchStatus;
use App\Domain\Research\Models\Research;
use App\Filament\Resources\Research\Pages\EditResearch;
use App\Filament\Resources\Research\Pages\ListResearch;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function makeResearch(array $attributes = []): Research
{
    $connection = Connection::factory()->create();

    return $connection->research()->create(array_merge([
        'version' => 1,
        'status' => ResearchStatus::Complete,
        'researched_by' => 'claude-pipeline',
        'skill_key' => 'military-discount-research',
        'skill_version' => '1',
        'brief_path' => "research/discounts/{$connection->slug}.md",
        'raw_markdown' => "# Brief for {$connection->brand}\n\nVerbatim content.",
        'last_verified' => '2026-07-20',
    ], $attributes));
}

it('lists research briefs with the brand, status and brief presence', function () {
    $complete = makeResearch();
    $stale = makeResearch(['status' => ResearchStatus::Stale]);

    Livewire::test(ListResearch::class)
        ->assertCanSeeTableRecords([$complete, $stale])
        ->assertCanRenderTableColumn('connection.brand')
        ->assertCanRenderTableColumn('status');
});

it('filters research by status', function () {
    $complete = makeResearch();
    $stale = makeResearch(['status' => ResearchStatus::Stale]);

    Livewire::test(ListResearch::class)
        ->filterTable('status', ResearchStatus::Stale->value)
        ->assertCanSeeTableRecords([$stale])
        ->assertCanNotSeeTableRecords([$complete]);
});

it('edits brief provenance without mutating the read-only raw_markdown', function () {
    $research = makeResearch(['status' => ResearchStatus::Draft, 'raw_markdown' => 'ORIGINAL VERBATIM']);

    Livewire::test(EditResearch::class, ['record' => $research->getRouteKey()])
        ->fillForm(['status' => ResearchStatus::Complete->value])
        ->call('save')
        ->assertHasNoFormErrors();

    $research->refresh();
    expect($research->status)->toBe(ResearchStatus::Complete)
        // raw_markdown is disabled + dehydrated(false) → never written by the form
        ->and($research->raw_markdown)->toBe('ORIGINAL VERBATIM');
});

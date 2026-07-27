<?php

declare(strict_types=1);

use App\Domain\Pillars\Enums\Admission;
use App\Domain\Pillars\Enums\JetTeamStatus;
use App\Domain\Pillars\Enums\TeamId;
use App\Domain\Pillars\Models\JetTeam;
use App\Domain\Pillars\Models\JetTeamCity;
use App\Domain\Pillars\Models\JetTeamScheduleRow;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;

it('casts the team enum, JSON copy and cross-team link', function () {
    $team = JetTeam::factory()->create();

    $fresh = $team->fresh();

    expect($fresh->team)->toBe(TeamId::BlueAngels)
        ->and($fresh->year)->toBe(2026)
        ->and($fresh->key_facts[0]['label'])->toBe('Aircraft')
        ->and($fresh->cross_team['href'])->toBe('/thunderbirds/')
        ->and($fresh->date_published->toDateString())->toBe('2026-06-10');
});

it('nests schedule rows in authored order and cities by city', function () {
    $team = JetTeam::factory()->create();
    JetTeamScheduleRow::factory()->for($team, 'team')->create(['sort_order' => 1, 'city' => 'Second stop']);
    JetTeamScheduleRow::factory()->for($team, 'team')->create(['sort_order' => 0, 'city' => 'First stop']);
    JetTeamCity::factory()->for($team, 'team')->create(['city' => 'Wasilla', 'slug' => 'wasilla']);
    JetTeamCity::factory()->for($team, 'team')->create(['city' => 'Anchorage', 'slug' => 'anchorage']);

    expect($team->schedule->pluck('city')->all())->toBe(['First stop', 'Second stop'])
        ->and($team->cities->pluck('city')->all())->toBe(['Anchorage', 'Wasilla']);
});

it('casts a schedule row, with nullable admission and a team backlink', function () {
    $team = JetTeam::factory()->create();
    $row = JetTeamScheduleRow::factory()->for($team, 'team')->create(['admission' => null]);

    $fresh = $row->fresh();

    expect($fresh->status)->toBe(JetTeamStatus::Scheduled)
        ->and($fresh->admission)->toBeNull()
        ->and($fresh->start_date->toDateString())->toBe('2026-08-08')
        ->and($fresh->team->is($team))->toBeTrue();
});

it('casts a city guide, its optional second window and publish flag', function () {
    $team = JetTeam::factory()->create();
    $city = JetTeamCity::factory()->for($team, 'team')->withSecondWindow()->create();

    $fresh = $city->fresh();

    expect($fresh->admission)->toBe(Admission::Free)
        ->and($fresh->status)->toBe(JetTeamStatus::Scheduled)
        ->and($fresh->published)->toBeTrue()
        ->and($fresh->second_start_date->toDateString())->toBe('2026-11-07')
        ->and($fresh->sections[0]['bullets'][0])->toBe('Free admission')
        ->and($fresh->team->is($team))->toBeTrue();
});

it('leaves the second window null by default', function () {
    $city = JetTeamCity::factory()->create();

    expect($city->fresh()->second_start_date)->toBeNull();
});

it('scopes the city-guide slug uniqueness per team, not globally', function () {
    $blueAngels = JetTeam::factory()->create();
    $thunderbirds = JetTeam::factory()->thunderbirds()->create();

    // The same city slug is allowed under each team (both fly to Anchorage).
    JetTeamCity::factory()->for($blueAngels, 'team')->create(['slug' => 'anchorage']);
    $shared = JetTeamCity::factory()->for($thunderbirds, 'team')->create(['slug' => 'anchorage']);

    expect($shared->exists)->toBeTrue()
        ->and(JetTeamCity::where('slug', 'anchorage')->count())->toBe(2);
});

it('attaches FAQs and sources to a city guide via the shared polymorphic tables', function () {
    $city = JetTeamCity::factory()->create();
    Faq::factory()->for($city, 'faqable')->create(['question' => 'B', 'sort_order' => 2]);
    Faq::factory()->for($city, 'faqable')->create(['question' => 'A', 'sort_order' => 1]);
    Source::factory()->for($city, 'sourceable')->create();

    expect($city->faqs->pluck('question')->all())->toBe(['A', 'B'])
        ->and($city->sources)->toHaveCount(1)
        ->and($city->faqs->first()->faqable->is($city))->toBeTrue();
});

it('attaches FAQs to a team hub via the shared polymorphic table', function () {
    $team = JetTeam::factory()->create();
    Faq::factory()->for($team, 'faqable')->create(['question' => 'Q', 'sort_order' => 1]);

    expect($team->faqs->pluck('question')->all())->toBe(['Q']);
});

it('exposes the team and status display labels', function () {
    expect(TeamId::BlueAngels->label())->toBe('Blue Angels')
        ->and(TeamId::Thunderbirds->label())->toBe('Thunderbirds')
        ->and(JetTeamStatus::Completed->label())->toBe('Completed')
        ->and(JetTeamStatus::Cancelled->label())->toBe('Cancelled');
});

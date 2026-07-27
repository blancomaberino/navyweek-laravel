<?php

declare(strict_types=1);

use App\Domain\Pillars\Enums\FleetWeekSeason;
use App\Domain\Pillars\Enums\FleetWeekStatus;
use App\Domain\Pillars\Models\FleetWeek;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;

it('casts the season/status enums, flex flags and block JSON', function () {
    $fw = FleetWeek::factory()->create();

    $fresh = $fw->fresh();

    expect($fresh->season)->toBe(FleetWeekSeason::Fall)
        ->and($fresh->status)->toBe(FleetWeekStatus::Scheduled)
        ->and($fresh->has_official_fleet_week)->toBeTrue()
        ->and($fresh->has_air_show)->toBeTrue()
        ->and($fresh->year)->toBe(2026)
        ->and($fresh->schedule[0]['event'])->toBe('Air show + Parade of Ships')
        ->and($fresh->airshow['performers'][0]['name'])->toBe('Blue Angels')
        ->and($fresh->festival['eventStatus'])->toBe('EventScheduled')
        ->and($fresh->date_published->toDateString())->toBe('2026-06-10');
});

it('builds a Tier-3 city with the optional blocks nulled out', function () {
    $fw = FleetWeek::factory()->tierThree()->create();

    $fresh = $fw->fresh();

    expect($fresh->status)->toBe(FleetWeekStatus::None)
        ->and($fresh->status->hasOfficialEvent())->toBeFalse()
        ->and($fresh->has_official_fleet_week)->toBeFalse()
        ->and($fresh->airshow)->toBeNull()
        ->and($fresh->festival)->toBeNull();
});

it('attaches FAQs and sources via the shared polymorphic tables, in order', function () {
    $fw = FleetWeek::factory()->create();
    Faq::factory()->for($fw, 'faqable')->create(['question' => 'B', 'sort_order' => 2]);
    Faq::factory()->for($fw, 'faqable')->create(['question' => 'A', 'sort_order' => 1]);
    Source::factory()->for($fw, 'sourceable')->create();

    expect($fw->faqs->pluck('question')->all())->toBe(['A', 'B'])
        ->and($fw->sources)->toHaveCount(1)
        ->and($fw->faqs->first()->faqable->is($fw))->toBeTrue();
});

it('exposes the season and status display labels', function () {
    expect(FleetWeekSeason::Fall->label())->toBe('Fall')
        ->and(FleetWeekStatus::OffSeason->label())->toBe('Off-season')
        ->and(FleetWeekStatus::Confirmed->hasOfficialEvent())->toBeTrue();
});

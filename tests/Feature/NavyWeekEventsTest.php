<?php

declare(strict_types=1);

use App\Domain\Pillars\Enums\NavyWeekSourceLevel;
use App\Domain\Pillars\Enums\NavyWeekStatus;
use App\Domain\Pillars\Models\NavyWeekEvent;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;

it('casts the core scalars, status enum and leaves city detail null by default', function () {
    $event = NavyWeekEvent::factory()->create();

    $fresh = $event->fresh();

    expect($fresh->status)->toBe(NavyWeekStatus::Upcoming)
        ->and($fresh->first_time)->toBeTrue()
        ->and($fresh->start_date->toDateString())->toBe('2026-01-26')
        ->and($fresh->lat)->toBeString()
        ->and($fresh->venues)->toBeNull()
        ->and($fresh->daily_schedule)->toBeNull();
});

it('casts the rich city-detail JSON block when populated', function () {
    $event = NavyWeekEvent::factory()->withCityDetail()->create();

    $fresh = $event->fresh();

    expect($fresh->navy_assets)->toBe(['USS Example'])
        ->and($fresh->venues[0]['name'])->toBe('City Hall')
        ->and($fresh->venues[0]['source_level'])->toBe('navco')
        ->and($fresh->daily_schedule[0]['items'][0]['title'])->toBe('Opening ceremony')
        ->and($fresh->last_verified_at->toDateString())->toBe('2026-07-13');
});

it('derives isFirstTimeLocation from first_time OR first_time_location', function () {
    expect(NavyWeekEvent::factory()->create(['first_time' => true, 'first_time_location' => null])->isFirstTimeLocation())->toBeTrue()
        ->and(NavyWeekEvent::factory()->create(['first_time' => false, 'first_time_location' => true])->isFirstTimeLocation())->toBeTrue()
        ->and(NavyWeekEvent::factory()->create(['first_time' => false, 'first_time_location' => false])->isFirstTimeLocation())->toBeFalse();
});

it('attaches FAQs and official sources via the shared polymorphic tables, in order', function () {
    $event = NavyWeekEvent::factory()->create();
    Faq::factory()->for($event, 'faqable')->create(['question' => 'B', 'sort_order' => 2]);
    Faq::factory()->for($event, 'faqable')->create(['question' => 'A', 'sort_order' => 1]);
    Source::factory()->for($event, 'sourceable')->create();

    expect($event->faqs->pluck('question')->all())->toBe(['A', 'B'])
        ->and($event->sources)->toHaveCount(1)
        ->and($event->faqs->first()->faqable->is($event))->toBeTrue();
});

it('exposes the status display label', function () {
    expect(NavyWeekStatus::Active->label())->toBe('Active');
});

it('exposes source-level labels and descriptions for the confidence badge', function () {
    expect(NavyWeekSourceLevel::Navco->label())->toBe('NAVCO-confirmed')
        ->and(NavyWeekSourceLevel::Navco->description())->toContain('Navy Office of Community Outreach')
        ->and(NavyWeekSourceLevel::Anchor->label())->toBe('Anchor-event-confirmed')
        ->and(NavyWeekSourceLevel::Local->label())->toBe('Local context — unverified');
});

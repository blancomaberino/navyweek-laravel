<?php

declare(strict_types=1);

use App\Domain\Pillars\Enums\BaseType;
use App\Domain\Pillars\Enums\CombatantCommand;
use App\Domain\Pillars\Enums\RegionType;
use App\Domain\Pillars\Models\Base;
use App\Domain\Pillars\Models\OverseasCountry;
use App\Domain\Pillars\Models\UsState;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;

it('casts enums, JSON lists, decimals and dates', function () {
    $base = Base::factory()->create([
        'type' => BaseType::NavalAirStation,
        'aka' => ['NAS Example'],
        'key_facts' => [['label' => 'Established', 'value' => '1917']],
    ]);

    $fresh = $base->fresh();

    expect($fresh->type)->toBe(BaseType::NavalAirStation)
        ->and($fresh->region_type)->toBe(RegionType::State)
        ->and($fresh->aka)->toBe(['NAS Example'])
        ->and($fresh->key_facts)->toBe([['label' => 'Established', 'value' => '1917']])
        ->and($fresh->established)->toBeInt()
        ->and($fresh->lat)->toBeString()
        ->and($fresh->last_updated->toDateString())->toBe('2026-07-01');
});

it('defaults to a state-based CONUS base and flips to overseas on request', function () {
    expect(Base::factory()->create()->isOverseas())->toBeFalse();

    $overseas = Base::factory()->overseas()->create();
    expect($overseas->region_type)->toBe(RegionType::Country)
        ->and($overseas->isOverseas())->toBeTrue()
        ->and($overseas->region)->toBe(CombatantCommand::Pacom)
        ->and($overseas->state)->toBeNull();

    $territory = Base::factory()->overseas(RegionType::Territory)->create();
    expect($territory->region_type)->toBe(RegionType::Territory)
        ->and($territory->isOverseas())->toBeTrue();
});

it('belongs to a U.S. state joined by slug, and the state has many bases', function () {
    $state = UsState::factory()->create(['slug' => 'virginia', 'name' => 'Virginia', 'abbr' => 'VA']);
    $base = Base::factory()->create(['state' => 'virginia']);

    expect($base->usState->is($state))->toBeTrue()
        ->and($state->bases->pluck('id')->all())->toContain($base->id);
});

it('belongs to an overseas country joined by slug', function () {
    $country = OverseasCountry::factory()->create(['slug' => 'japan', 'region' => CombatantCommand::Pacom]);
    $base = Base::factory()->overseas()->create(['country_slug' => 'japan']);

    expect($base->overseasCountry->is($country))->toBeTrue()
        ->and($country->bases->pluck('id')->all())->toContain($base->id);
});

it('casts the overseas country region enum and territory flag', function () {
    $country = OverseasCountry::factory()->create([
        'region' => CombatantCommand::Eucom,
        'is_us_territory' => true,
    ]);

    expect($country->fresh()->region)->toBe(CombatantCommand::Eucom)
        ->and($country->fresh()->is_us_territory)->toBeTrue();
});

it('exposes installation-type and combatant-command display labels', function () {
    expect(BaseType::NavalAirStation->label())->toBe('Naval Air Station')
        ->and(BaseType::NavalAirStation->pluralLabel())->toBe('Naval Air Stations')
        ->and(BaseType::NavalStation->label())->toBe('Naval Station')
        ->and(BaseType::SubmarineBase->pluralLabel())->toBe('Submarine Bases')
        ->and(CombatantCommand::Pacom->label())->toContain('PACOM');
});

it('attaches FAQs and sources via the shared polymorphic tables, in order', function () {
    $base = Base::factory()->create();
    Faq::factory()->for($base, 'faqable')->create(['question' => 'B', 'sort_order' => 2]);
    Faq::factory()->for($base, 'faqable')->create(['question' => 'A', 'sort_order' => 1]);
    Source::factory()->for($base, 'sourceable')->create();

    expect($base->faqs->pluck('question')->all())->toBe(['A', 'B'])
        ->and($base->sources)->toHaveCount(1)
        ->and($base->faqs->first()->faqable->is($base))->toBeTrue()
        ->and($base->sources->first()->sourceable->is($base))->toBeTrue();
});

<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\MealEligibility;
use App\Domain\Catalog\Enums\MealRedemption;
use App\Domain\Catalog\Enums\MealStatus;
use App\Domain\Catalog\Models\VeteransDayMeal;
use App\Domain\Crm\Models\Connection;

it('casts eligibility as an enum collection plus the redemption and status enums', function () {
    $meal = VeteransDayMeal::factory()->create([
        'eligibility' => [MealEligibility::Veteran, MealEligibility::Guard],
        'redemption' => MealRedemption::Both,
    ]);

    $fresh = $meal->fresh();

    expect($fresh->eligibility)->toHaveCount(2)
        ->and($fresh->eligibility->first())->toBe(MealEligibility::Veteran)
        ->and($fresh->eligibility->last())->toBe(MealEligibility::Guard)
        ->and($fresh->redemption)->toBe(MealRedemption::Both)
        ->and($fresh->status)->toBe(MealStatus::Verified)
        ->and($fresh->dependents_eligible)->toBeFalse()
        ->and($fresh->last_verified_at->toDateString())->toBe('2026-06-29');
});

it('gates rendering on verified status AND a primary source', function () {
    expect(VeteransDayMeal::factory()->create()->isRenderable())->toBeTrue()
        ->and(VeteransDayMeal::factory()->pending()->create()->isRenderable())->toBeFalse()
        ->and(VeteransDayMeal::factory()->discontinued()->create()->isRenderable())->toBeFalse()
        ->and(VeteransDayMeal::factory()->create(['source_url' => ''])->isRenderable())->toBeFalse();
});

it('links to the brand discount guide by slug when one exists', function () {
    $connection = Connection::factory()->create(['slug' => 'texas-roadhouse']);
    $mapped = VeteransDayMeal::factory()->create(['discount_slug' => 'texas-roadhouse']);
    $unmapped = VeteransDayMeal::factory()->create(['discount_slug' => null]);

    expect($mapped->discount->is($connection))->toBeTrue()
        ->and($unmapped->discount)->toBeNull();
});

it('exposes eligibility and redemption display labels', function () {
    expect(MealEligibility::Guard->label())->toBe('National Guard')
        ->and(MealRedemption::Both->label())->toBe('Dine-in or takeout')
        ->and(MealStatus::Discontinued->label())->toBe('Discontinued');
});

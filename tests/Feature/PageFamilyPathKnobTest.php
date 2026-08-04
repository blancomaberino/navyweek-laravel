<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\LocalDiscount;
use App\Domain\Catalog\Pages\GenerateLocalDiscountPagesAction;
use App\Domain\Pillars\Models\AirShow;
use App\Domain\Pillars\Models\Base;
use App\Domain\Pillars\Models\FleetWeek;
use App\Domain\Pillars\Models\NavyWeekEvent;
use App\Domain\Pillars\Pages\GenerateAirShowPagesAction;
use App\Domain\Pillars\Pages\GenerateBasePagesAction;
use App\Domain\Pillars\Pages\GenerateFleetWeekPagesAction;
use App\Domain\Pillars\Pages\GenerateNavyWeekPagesAction;
use App\Domain\Pillars\Pages\GenerateRankPagesAction;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Models\Redirect;
use App\Domain\Publishing\Pages\GenerateAuthorPagesAction;
use App\Domain\Publishing\Pages\GenerateDiscountIndexPageAction;
use App\Models\User;

/**
 * Behavioral guard for the "single-knob URL family" contract: for every generated page
 * family, changing its `config('publishing.paths.*')` root and re-running the generator
 * MOVES the page to the new prefix and auto-creates the 301. This tests the property we
 * actually care about (flexibility), so a regression to a hardcoded default fails here.
 *
 * Keyed by the config family name so the coverage test below can assert EVERY configured
 * family has a case — that is what makes "add a new family" fail-closed: add a
 * `config('publishing.paths')` entry without a case here and the suite goes red.
 *
 * @return array<string, array{configKey: string, seed: Closure, action: class-string, generationKey: string, oldPath: string, newRoot: string, newPath: string}>
 */
function knobFamilyCases(): array
{
    return [
        'bases' => [
            'configKey' => 'bases',
            'seed' => fn () => Base::factory()->create(['slug' => 'knob-base']),
            'action' => GenerateBasePagesAction::class,
            'generationKey' => 'base:knob-base',
            'oldPath' => '/navy-bases/knob-base/',
            'newRoot' => '/bases-x/',
            'newPath' => '/bases-x/knob-base/',
        ],
        'ranks' => [
            'configKey' => 'ranks',
            'seed' => fn () => null, // the consolidated list page needs no aggregate rows
            'action' => GenerateRankPagesAction::class,
            'generationKey' => 'rank-list',
            'oldPath' => '/navy-ranks/',
            'newRoot' => '/ranks-x/',
            'newPath' => '/ranks-x/',
        ],
        'ratings' => [
            'configKey' => 'ratings',
            'seed' => fn () => null,
            'action' => GenerateRankPagesAction::class,
            'generationKey' => 'rating-list',
            'oldPath' => '/navy-ratings/',
            'newRoot' => '/ratings-x/',
            'newPath' => '/ratings-x/',
        ],
        'air_shows' => [
            'configKey' => 'air_shows',
            'seed' => fn () => AirShow::factory()->create(['slug' => 'knob-show']),
            'action' => GenerateAirShowPagesAction::class,
            'generationKey' => 'air-show:knob-show',
            'oldPath' => '/air-show/knob-show/',
            'newRoot' => '/airshows-x/',
            'newPath' => '/airshows-x/knob-show/',
        ],
        'fleet_weeks' => [
            'configKey' => 'fleet_weeks',
            'seed' => fn () => FleetWeek::factory()->create(['slug' => 'knob-week']),
            'action' => GenerateFleetWeekPagesAction::class,
            'generationKey' => 'fleetweek:knob-week',
            'oldPath' => '/fleetweek/knob-week/',
            'newRoot' => '/fleet-x/',
            'newPath' => '/fleet-x/knob-week/',
        ],
        'navy_week_cities' => [
            'configKey' => 'navy_week_cities',
            'seed' => fn () => NavyWeekEvent::factory()->create(['slug' => 'knob-city']),
            'action' => GenerateNavyWeekPagesAction::class,
            'generationKey' => 'navy-week-city:knob-city',
            'oldPath' => '/city/knob-city/',
            'newRoot' => '/cities-x/',
            'newPath' => '/cities-x/knob-city/',
        ],
        'local_discounts' => [
            'configKey' => 'local_discounts',
            'seed' => fn () => LocalDiscount::factory()->create([
                'state' => 'texas', 'city' => 'houston', 'business_slug' => 'knob-biz',
            ]),
            'action' => GenerateLocalDiscountPagesAction::class,
            'generationKey' => 'local-discount:texas:houston:knob-biz',
            'oldPath' => '/discounts/texas/houston/knob-biz/',
            'newRoot' => '/local-x/',
            'newPath' => '/local-x/texas/houston/knob-biz/',
        ],
        'discounts' => [
            'configKey' => 'discounts',
            'seed' => fn () => null,
            'action' => GenerateDiscountIndexPageAction::class,
            'generationKey' => 'discount-index',
            'oldPath' => '/discount/',
            'newRoot' => '/deals-x/',
            'newPath' => '/deals-x/',
        ],
        'authors' => [
            'configKey' => 'authors',
            // Force a known id so the generation_key (`author:{id}`, the immutable identity)
            // is deterministic; forceCreate bypasses mass-assignment guarding to set the PK.
            'seed' => fn () => User::forceCreate([
                'id' => 987654,
                'name' => 'Knob Author',
                'email' => 'knob-author@example.test',
                'slug' => 'knob-author',
                'password' => 'x',
            ]),
            'action' => GenerateAuthorPagesAction::class,
            'generationKey' => 'author:987654',
            'oldPath' => '/authors/knob-author/',
            'newRoot' => '/writers-x/',
            'newPath' => '/writers-x/knob-author/',
        ],
    ];
}

dataset('knob families', knobFamilyCases());

/**
 * Families whose ROOT path is already configured (so cross-links elsewhere resolve
 * through PagePaths instead of a hardcoded literal) but which have no generator
 * yet — they are still being built out. A deliberate, reviewed opt-out: delete the
 * entry and add a real knobFamilyCases() row the moment the family's generator
 * lands, or the guard stops meaning anything.
 *
 * @return list<string>
 */
function knobPendingFamilies(): array
{
    return ['navy_reference', 'designators'];
}

it('has a knob-test case for every configured page family', function () {
    // Fail-closed: a new config('publishing.paths') family with no case above turns this
    // red, forcing a behavioral test (and thus PagePaths usage) for the new family.
    expect([...array_keys(knobFamilyCases()), ...knobPendingFamilies()])
        ->toEqualCanonicalizing(array_keys(config('publishing.paths')));
});

it('has no pending family that already has a generator', function () {
    // The pending list is temporary scaffolding — once a family can generate pages it
    // must graduate to a real knob case.
    expect(array_intersect(knobPendingFamilies(), array_keys(knobFamilyCases())))->toBe([]);
});

it('moves a family to a new prefix and 301s the old path when its config knob changes', function (
    string $configKey,
    Closure $seed,
    string $action,
    string $generationKey,
    string $oldPath,
    string $newRoot,
    string $newPath,
) {
    $seed();
    app($action)();

    $page = Page::query()->where('generation_key', $generationKey)->firstOrFail();
    expect($page->url_path)->toBe($oldPath)
        ->and($page->url_path_is_custom)->toBeFalse();

    // Flip the single knob and regenerate.
    config()->set("publishing.paths.{$configKey}", $newRoot);
    app($action)();

    $page->refresh();
    expect($page->url_path)->toBe($newPath)
        // Moved, never duplicated.
        ->and(Page::query()->where('generation_key', $generationKey)->count())->toBe(1)
        // …and the old path now 301s to the new one, no deploy.
        ->and(Redirect::query()->where('from_path', $oldPath)->where('to_path', $newPath)->exists())->toBeTrue();
})->with('knob families');

<?php

declare(strict_types=1);

use App\Domain\Pillars\Enums\DesignatorCommunity;
use App\Domain\Pillars\Enums\HistoricRatingEra;
use App\Domain\Pillars\Enums\RankCategory;
use App\Domain\Pillars\Enums\RatingCommunity;
use App\Domain\Pillars\Models\Base;
use App\Domain\Pillars\Models\Rank;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;

it('casts the common columns, discriminator and officer variant', function () {
    $rank = Rank::factory()->create(['responsibilities' => ['Lead', 'Mentor']]);

    $fresh = $rank->fresh();

    expect($fresh->category)->toBe(RankCategory::OfficerCommissioned)
        ->and($fresh->responsibilities)->toBe(['Lead', 'Mentor'])
        ->and($fresh->pay_range)->toBeArray()
        ->and($fresh->is_flag_officer)->toBeFalse()
        ->and($fresh->isRating())->toBeFalse()
        ->and($fresh->last_updated->toDateString())->toBe('2026-07-01');
});

it('casts the two disjoint community enums on their own categories', function () {
    $designator = Rank::factory()->designator()->create();
    expect($designator->fresh()->designator_community)->toBe(DesignatorCommunity::UnrestrictedLine)
        ->and($designator->rating_community)->toBeNull()
        ->and($designator->isRating())->toBeFalse();

    $rating = Rank::factory()->ratingActive()->create();
    expect($rating->fresh()->rating_community)->toBe(RatingCommunity::General)
        ->and($rating->designator_community)->toBeNull()
        ->and($rating->isRating())->toBeTrue();
});

it('builds warrant and enlisted entries with the right discriminator and flags', function () {
    $warrant = Rank::factory()->warrant()->create();
    expect($warrant->fresh()->category)->toBe(RankCategory::OfficerWarrant)
        ->and($warrant->is_flag_officer)->toBeNull()
        ->and($warrant->nato_code)->toBe('WO-2');

    $enlisted = Rank::factory()->enlisted()->create();
    expect($enlisted->fresh()->category)->toBe(RankCategory::EnlistedPaygrade)
        ->and($enlisted->is_chief)->toBeTrue()
        ->and($enlisted->is_flag_officer)->toBeNull();
});

it('casts era_tags as a HistoricRatingEra collection on a historical rating', function () {
    $historical = Rank::factory()->ratingHistorical()->create();

    $fresh = $historical->fresh();

    expect($fresh->category)->toBe(RankCategory::RatingHistorical)
        ->and($fresh->isRating())->toBeTrue()
        ->and($fresh->era_tags)->toHaveCount(2)
        ->and($fresh->era_tags->first())->toBe(HistoricRatingEra::ColdWar)
        ->and($fresh->decommissioned_year)->toBe(2016);
});

it('links to the next and previous entry in its sequence by slug', function () {
    $ensign = Rank::factory()->create(['slug' => 'ensign']);
    $ltjg = Rank::factory()->create(['slug' => 'lieutenant-junior-grade', 'previous_slug' => 'ensign']);
    $ensign->update(['next_slug' => 'lieutenant-junior-grade']);

    expect($ensign->nextRank->is($ltjg))->toBeTrue()
        ->and($ltjg->previousRank->is($ensign))->toBeTrue();
});

it('links a historical rating to the rating it merged into, by slug', function () {
    $active = Rank::factory()->ratingActive()->create(['slug' => 'active-rating']);
    $historical = Rank::factory()->ratingHistorical()->create(['merged_into_slug' => 'active-rating']);

    expect($historical->mergedIntoRank->is($active))->toBeTrue();
});

it('links to a related base and an A-school base by slug', function () {
    $base = Base::factory()->create(['slug' => 'naval-station-great-lakes']);
    $rank = Rank::factory()->create(['related_base_slug' => 'naval-station-great-lakes']);
    $rating = Rank::factory()->ratingActive()->create(['a_school_location_slug' => 'naval-station-great-lakes']);

    expect($rank->relatedBase->is($base))->toBeTrue()
        ->and($rating->aSchoolBase->is($base))->toBeTrue();
});

it('attaches FAQs and sources via the shared polymorphic tables, in order', function () {
    $rank = Rank::factory()->create();
    Faq::factory()->for($rank, 'faqable')->create(['question' => 'B', 'sort_order' => 2]);
    Faq::factory()->for($rank, 'faqable')->create(['question' => 'A', 'sort_order' => 1]);
    Source::factory()->for($rank, 'sourceable')->create();

    expect($rank->faqs->pluck('question')->all())->toBe(['A', 'B'])
        ->and($rank->sources)->toHaveCount(1)
        ->and($rank->faqs->first()->faqable->is($rank))->toBeTrue();
});

it('exposes category and community display labels', function () {
    expect(RankCategory::OfficerCommissioned->label())->toBe('Commissioned Officer')
        ->and(DesignatorCommunity::StaffCorps->shortLabel())->toBe('Staff')
        ->and(RatingCommunity::Nuclear->label())->toBe('Nuclear Power')
        ->and(HistoricRatingEra::ColdWar->label())->toContain('Cold War');
});

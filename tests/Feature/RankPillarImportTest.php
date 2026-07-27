<?php

declare(strict_types=1);

use App\Domain\Pillars\Enums\DesignatorCommunity;
use App\Domain\Pillars\Enums\HistoricRatingEra;
use App\Domain\Pillars\Enums\RankCategory;
use App\Domain\Pillars\Enums\RatingCommunity;
use App\Domain\Pillars\Import\RankPillarImporter;
use App\Domain\Pillars\Models\Rank;
use App\Domain\Shared\Import\SeedArtifact;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;

/** @return array<int, array<string, mixed>> */
function ranksArtifact(): array
{
    return SeedArtifact::read('ranks');
}

it('imports every rank row from the committed artifact', function () {
    $counts = app(RankPillarImporter::class)->import();

    expect($counts['ranks'])->toBe(count(ranksArtifact()))
        ->and(Rank::count())->toBe(count(ranksArtifact()))
        ->and(Rank::count())->toBeGreaterThan(0);
});

it('imports all six categories', function () {
    app(RankPillarImporter::class)->import();

    // Every discriminator value in the artifact must survive the enum cast.
    $categories = Rank::query()->pluck('category')->unique()->values();

    expect($categories)->toHaveCount(6)
        ->each->toBeInstanceOf(RankCategory::class);
});

it('reproduces the officer next/previous slug consolidation and self-ref link', function () {
    app(RankPillarImporter::class)->import();

    $ensign = Rank::query()->where('slug', 'ensign')->sole();

    expect($ensign->category)->toBe(RankCategory::OfficerCommissioned)
        ->and($ensign->next_slug)->toBe('lieutenant-junior-grade')
        ->and($ensign->nextRank)->not->toBeNull()
        ->and($ensign->nextRank->slug)->toBe('lieutenant-junior-grade');
});

it('splits community into the designator vs rating enum by category', function () {
    app(RankPillarImporter::class)->import();

    $designator = Rank::query()->where('category', RankCategory::OfficerDesignator->value)->first();
    $rating = Rank::query()->where('category', RankCategory::RatingActive->value)->first();

    expect($designator->designator_community)->toBeInstanceOf(DesignatorCommunity::class)
        ->and($designator->rating_community)->toBeNull()
        ->and($designator->career_path)->toBeArray()->not->toBeEmpty()
        ->and($rating->rating_community)->toBeInstanceOf(RatingCommunity::class)
        ->and($rating->designator_community)->toBeNull()
        ->and($rating->isRating())->toBeTrue();
});

it('casts era_tags as a HistoricRatingEra collection and links merged_into by slug', function () {
    app(RankPillarImporter::class)->import();

    // personnelman: a historical rating that merged into yeoman (2000s consolidation).
    $historical = Rank::query()->where('slug', 'personnelman')->sole();

    expect($historical->category)->toBe(RankCategory::RatingHistorical)
        ->and($historical->era_tags)->not->toBeEmpty()
        ->and($historical->era_tags->first())->toBeInstanceOf(HistoricRatingEra::class)
        ->and($historical->decommissioned_year)->toBeInt()
        ->and($historical->mergedIntoRank)->not->toBeNull()
        ->and($historical->mergedIntoRank->slug)->toBe('yeoman');
});

it('populates the officer and enlisted STI variant columns', function () {
    app(RankPillarImporter::class)->import();

    // Officer-commissioned flag officer: the is_flag_officer boolean is set.
    $admiral = Rank::query()->where('slug', 'rear-admiral-lower-half')->sole();
    expect($admiral->category)->toBe(RankCategory::OfficerCommissioned)
        ->and($admiral->is_flag_officer)->toBeTrue()
        ->and($admiral->is_chief)->toBeNull();

    // Enlisted paygrade: is_chief boolean + the community_variants JSON.
    $chief = Rank::query()->where('slug', 'chief-petty-officer')->sole();
    expect($chief->category)->toBe(RankCategory::EnlistedPaygrade)
        ->and($chief->is_chief)->toBeTrue()
        ->and($chief->is_flag_officer)->toBeNull();

    $seamanApprentice = Rank::query()->where('slug', 'seaman-apprentice')->sole();
    expect($seamanApprentice->community_variants)->toBeArray()->not->toBeEmpty();
});

it('attaches FAQs and sources from the artifact, in order', function () {
    app(RankPillarImporter::class)->import();

    $row = collect(ranksArtifact())->firstWhere('slug', 'ensign');
    $ensign = Rank::query()->where('slug', 'ensign')->sole();

    expect($ensign->faqs()->count())->toBe(count($row['faqs']))
        ->and($ensign->sources()->count())->toBe(count($row['sources']))
        ->and($ensign->faqs->first()->question)->toBe($row['faqs'][0]['question']);
});

it('is idempotent — re-running upserts without duplicating rows or children', function () {
    $importer = app(RankPillarImporter::class);
    $importer->import();

    $ranks = Rank::count();
    $faqs = Faq::count();
    $sources = Source::count();

    $importer->import();

    expect(Rank::count())->toBe($ranks)
        ->and(Faq::count())->toBe($faqs)
        ->and(Source::count())->toBe($sources);
});

it('runs end-to-end via the import:ranks artisan command', function () {
    $this->artisan('import:ranks')->assertSuccessful();

    expect(Rank::count())->toBe(count(ranksArtifact()));
});

<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Offer;
use App\Domain\Crm\Models\Connection;
use App\Domain\Research\Enums\ResearchedBy;
use App\Domain\Research\Enums\ResearchStatus;
use App\Domain\Research\Models\Research;
use App\Domain\Research\Models\Skill;
use App\Domain\Shared\Enums\ConfidenceLevel;

it('casts enums, JSON facts and dates', function () {
    $research = Research::factory()->create([
        'status' => ResearchStatus::Complete,
        'confidence_overall' => ConfidenceLevel::Medium,
        'researched_by' => ResearchedBy::Human,
    ]);

    $fresh = $research->fresh();

    expect($fresh->status)->toBe(ResearchStatus::Complete)
        ->and($fresh->confidence_overall)->toBe(ConfidenceLevel::Medium)
        ->and($fresh->researched_by)->toBe(ResearchedBy::Human)
        ->and($fresh->verified_facts[0]['value'])->toBe('10% off')
        ->and($fresh->last_verified?->toDateString())->toBe('2026-06-23')
        ->and($fresh->version)->toBe(1);
});

it('always stores the raw brief markdown verbatim (zero loss)', function () {
    $markdown = "# Nike Brief\n\n## Verified Facts\n\n| Fact | Value |\n|---|---|\n| Discount | 10% |";
    $research = Research::factory()->create(['raw_markdown' => $markdown]);

    expect($research->fresh()->raw_markdown)->toBe($markdown);
});

it('belongs to a connection, which has many research briefs', function () {
    $connection = Connection::factory()->create();
    Research::factory()->for($connection)->create(['version' => 1]);
    Research::factory()->for($connection)->create(['version' => 2]);

    expect($connection->research)->toHaveCount(2)
        ->and($connection->research->first()->connection->is($connection))->toBeTrue();
});

it('can scope a brief to a specific offer', function () {
    $offer = Offer::factory()->create();
    $research = Research::factory()->for($offer->connection)->create(['offer_id' => $offer->id]);

    expect($research->offer->is($offer))->toBeTrue();
});

it('records every skill and version that contributed (provenance pivot)', function () {
    $research = Research::factory()->create();
    $facts = Skill::factory()->create(['key' => 'military-discount-research']);
    $geo = Skill::factory()->create(['key' => 'seo-geo']);

    $research->skills()->attach($facts, ['skill_version' => '3', 'used_for' => 'facts']);
    $research->skills()->attach($geo, ['skill_version' => '2', 'used_for' => 'citability']);

    $research->load('skills');

    expect($research->skills)->toHaveCount(2)
        ->and($research->skills->firstWhere('key', 'military-discount-research')->pivot->skill_version)->toBe('3')
        ->and($research->skills->firstWhere('key', 'seo-geo')->pivot->used_for)->toBe('citability');
});

it('cascades provenance pivot rows when research is deleted', function () {
    $research = Research::factory()->create();
    $research->skills()->attach(Skill::factory()->create(), ['skill_version' => '1', 'used_for' => 'facts']);

    $research->delete();

    expect(DB::table('research_skill')->count())->toBe(0);
});

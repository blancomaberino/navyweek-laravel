<?php

declare(strict_types=1);

use App\Domain\Pillars\Enums\Admission;
use App\Domain\Pillars\Enums\JetTeamStatus;
use App\Domain\Pillars\Enums\TeamId;
use App\Domain\Pillars\Import\JetTeamsImporter;
use App\Domain\Pillars\Models\JetTeam;
use App\Domain\Pillars\Models\JetTeamCity;
use App\Domain\Pillars\Models\JetTeamScheduleRow;
use App\Domain\Shared\Import\SeedArtifact;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;

/** @return array<int, array<string, mixed>> */
function jetArtifact(string $name): array
{
    return SeedArtifact::read($name);
}

it('imports both hubs, every schedule stop and the published cities', function () {
    $counts = app(JetTeamsImporter::class)->import();

    expect($counts['jet_teams'])->toBe(count(jetArtifact('jet-teams')))
        ->and($counts['jet_team_schedule'])->toBe(count(jetArtifact('jet-team-schedule')))
        ->and($counts['jet_team_cities'])->toBe(count(jetArtifact('jet-team-cities')))
        ->and(JetTeam::count())->toBe(2)
        ->and(JetTeamScheduleRow::count())->toBe(count(jetArtifact('jet-team-schedule')))->toBeGreaterThan(0)
        ->and(JetTeamCity::count())->toBe(count(jetArtifact('jet-team-cities')))->toBeGreaterThan(0);
});

it('maps the hub with its enum key, JSON blocks and FAQ-only children', function () {
    app(JetTeamsImporter::class)->import();

    $row = collect(jetArtifact('jet-teams'))->firstWhere('team', 'blue-angels');
    $ba = JetTeam::query()->where('team', 'blue-angels')->sole();

    expect($ba->team)->toBe(TeamId::BlueAngels)
        ->and($ba->intro)->toBeArray()->not->toBeEmpty()
        ->and($ba->key_facts)->toBeArray()
        // cross_team is a single object, not wrapped in an array
        ->and($ba->cross_team)->toBeArray()->toHaveKeys(['label', 'href'])
        ->and($ba->faqs()->count())->toBe(count($row['faqs']))
        // The hub carries no sources (TeamMeta has no sources field).
        ->and($ba->last_verified)->toBe($row['last_verified']);
});

it('links schedule stops to their team and keeps season order', function () {
    app(JetTeamsImporter::class)->import();

    $ba = JetTeam::query()->where('team', 'blue-angels')->sole();

    expect($ba->schedule()->count())->toBe(32);

    // schedule() is ordered by sort_order — the season order from the artifact.
    $orders = $ba->schedule->pluck('sort_order')->all();
    expect($orders)->toBe(range(0, 31));
});

it('preserves the sparse venue/admission and the status default vs overrides', function () {
    app(JetTeamsImporter::class)->import();

    $ba = JetTeam::query()->where('team', 'blue-angels')->sole();
    $anchorage = $ba->schedule()->where('slug', 'anchorage')->sole();

    // Only two of the 64 stops set venue/admission; this is one of them.
    expect($anchorage->venue)->toBe('Joint Base Elmendorf-Richardson')
        ->and($anchorage->admission)->toBe(Admission::Free)
        ->and($anchorage->status)->toBe(JetTeamStatus::Scheduled);

    // A stop with no admission stays null (not backfilled from the city guide).
    $noAdmission = JetTeamScheduleRow::query()->whereNull('admission')->first();
    expect($noAdmission)->not->toBeNull();

    $tb = JetTeam::query()->where('team', 'thunderbirds')->sole();
    $postponed = $tb->schedule()->where('slug', 'shaw-afb')->sole();
    expect($postponed->status)->toBe(JetTeamStatus::Postponed);
});

it('keeps a city that recurs in a season as two schedule rows (slug not unique)', function () {
    app(JetTeamsImporter::class)->import();

    $ba = JetTeam::query()->where('team', 'blue-angels')->sole();

    // Blue Angels visit Pensacola twice (July + the November homecoming).
    expect($ba->schedule()->where('slug', 'pensacola')->count())->toBe(2);
});

it('imports a published city guide with sources+publisher and the second-window nulls', function () {
    app(JetTeamsImporter::class)->import();

    $row = collect(jetArtifact('jet-team-cities'))->firstWhere('slug', 'anchorage');
    $anchorage = JetTeamCity::query()->where('slug', 'anchorage')->sole();

    // `team` on a city is the belongsTo hub; the natural-key enum lives on it.
    expect($anchorage->team->team)->toBe(TeamId::BlueAngels)
        ->and($anchorage->published)->toBeTrue()
        ->and($anchorage->admission)->toBe(Admission::Free)
        ->and($anchorage->sections)->toBeArray()->not->toBeEmpty()
        ->and($anchorage->second_dates_label)->toBeNull()
        ->and($anchorage->second_start_date)->toBeNull()
        ->and($anchorage->dek)->toBeNull()
        ->and($anchorage->h1)->toBe($row['h1'])
        ->and($anchorage->faqs()->count())->toBe(count($row['faqs']))
        ->and($anchorage->sources()->count())->toBe(count($row['sources']))
        // City sources keep their publisher provenance (unlike the bases shape).
        ->and($anchorage->sources->first()->publisher)->not->toBeNull();
});

it('imports a city with no sources without creating stray rows', function () {
    app(JetTeamsImporter::class)->import();

    $annapolis = JetTeamCity::query()->where('slug', 'annapolis')->sole();

    expect($annapolis->status)->toBe(JetTeamStatus::Completed)
        ->and($annapolis->sources()->count())->toBe(0)
        ->and($annapolis->faqs()->count())->toBeGreaterThan(0);
});

it('is idempotent — re-running replaces stops without duplicating rows or children', function () {
    $importer = app(JetTeamsImporter::class);
    $importer->import();

    $faqs = Faq::count();
    $sources = Source::count();

    $importer->import();

    expect(JetTeam::count())->toBe(2)
        ->and(JetTeamScheduleRow::count())->toBe(count(jetArtifact('jet-team-schedule')))
        ->and(JetTeamCity::count())->toBe(count(jetArtifact('jet-team-cities')))
        ->and(Faq::count())->toBe($faqs)
        ->and(Source::count())->toBe($sources);
});

it('runs end-to-end via the import:jet-teams artisan command', function () {
    $this->artisan('import:jet-teams')->assertSuccessful();

    expect(JetTeamScheduleRow::count())->toBe(count(jetArtifact('jet-team-schedule')));
});

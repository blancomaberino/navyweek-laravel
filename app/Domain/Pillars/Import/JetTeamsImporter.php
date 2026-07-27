<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Import;

use App\Domain\Pillars\Models\JetTeam;
use App\Domain\Shared\Import\SeedArtifact;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Stage-B importer for the jet-teams silo — the two team hubs, every season
 * schedule stop, and the published city guides. One transaction, idempotent, in
 * dependency order (hubs first so the children resolve their `jet_team_id` from
 * the `team` natural key the exporter carries on each child row).
 *
 * Idempotency differs by child:
 *  - hubs + cities have a natural unique key (`team`; `jet_team_id + slug`), so
 *    they upsert per row and replace their polymorphic FAQs/sources.
 *  - the schedule has NO natural unique key (a city slug recurs within a season
 *    and across teams), so each team's stops are replaced wholesale — delete all
 *    for the team, then re-insert in artifact (season) order.
 *
 * Upsert, not sync: a team/city absent from a later artifact is left in place.
 */
final class JetTeamsImporter
{
    /**
     * @return array<string, int> row counts by table
     */
    public function import(): array
    {
        return DB::transaction(fn (): array => [
            'jet_teams' => $this->importTeams(),
            'jet_team_schedule' => $this->importSchedule(),
            'jet_team_cities' => $this->importCities(),
        ]);
    }

    private function importTeams(): int
    {
        $rows = SeedArtifact::read('jet-teams');

        foreach ($rows as $row) {
            /** @var list<array<string, mixed>> $faqs */
            $faqs = $row['faqs'] ?? [];
            unset($row['faqs']);

            $team = JetTeam::query()->updateOrCreate(['team' => $row['team']], $row);

            $team->replaceFaqs($faqs);
        }

        return count($rows);
    }

    private function importSchedule(): int
    {
        $rows = SeedArtifact::read('jet-team-schedule');

        // Group stops by their team natural key, stripping the transient `team`
        // marker the exporter added (it is not a schedule column).
        $byTeam = [];
        foreach ($rows as $row) {
            $team = $row['team'] ?? null;
            if (! is_string($team)) {
                throw new RuntimeException('jet-team schedule row is missing its string `team` key.');
            }
            unset($row['team']);
            $byTeam[$team][] = $row;
        }

        foreach ($byTeam as $team => $stops) {
            $jetTeam = JetTeam::query()->where('team', $team)->sole();

            // No natural unique key on a stop → replace the team's set wholesale.
            $jetTeam->schedule()->delete();
            /** @var list<array<string, mixed>> $stops */
            $jetTeam->schedule()->createMany($stops);
        }

        return count($rows);
    }

    private function importCities(): int
    {
        $rows = SeedArtifact::read('jet-team-cities');

        foreach ($rows as $row) {
            /** @var list<array<string, mixed>> $faqs */
            $faqs = $row['faqs'] ?? [];
            /** @var list<array<string, mixed>> $sources */
            $sources = $row['sources'] ?? [];
            $teamKey = $row['team'];
            unset($row['faqs'], $row['sources'], $row['team']);

            $jetTeam = JetTeam::query()->where('team', $teamKey)->sole();
            $city = $jetTeam->cities()->updateOrCreate(['slug' => $row['slug']], $row);

            $city->replaceFaqs($faqs);
            $city->replaceSources($sources);
        }

        return count($rows);
    }
}

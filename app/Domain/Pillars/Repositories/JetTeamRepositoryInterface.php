<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Repositories;

use App\Domain\Pillars\Enums\TeamId;
use App\Domain\Pillars\Models\JetTeam;
use App\Domain\Pillars\Models\JetTeamCity;
use App\Domain\Pillars\Models\JetTeamScheduleRow;
use Illuminate\Support\Collection;

/**
 * Data access for the jet-teams silo (team hubs + schedule + published city
 * guides). Mirrors the legacy `jetteams/index.ts` helpers (`getTeamMeta`,
 * `getTeamMetaByBasePath`, `getTeamSchedule`, `getTeamPublishedCities`). Callers
 * depend on this interface; the Eloquent implementation is bound in
 * DomainServiceProvider.
 */
interface JetTeamRepositoryInterface
{
    public function findTeam(TeamId $team): ?JetTeam;

    /** A team hub by its URL base path, e.g. "/blue-angels". */
    public function findByBasePath(string $basePath): ?JetTeam;

    /**
     * Both team hubs.
     *
     * @return Collection<int, JetTeam>
     */
    public function allTeams(): Collection;

    /**
     * A team's full season schedule (every stop), in authored order.
     *
     * @return Collection<int, JetTeamScheduleRow>
     */
    public function schedule(TeamId $team): Collection;

    /**
     * A team's published city guides only (the render gate), ordered by city.
     *
     * @return Collection<int, JetTeamCity>
     */
    public function publishedCities(TeamId $team): Collection;

    /** A single city guide by team + slug (the `/{team}/{slug}/` route). */
    public function findCity(TeamId $team, string $slug): ?JetTeamCity;
}

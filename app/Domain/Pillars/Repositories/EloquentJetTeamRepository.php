<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Repositories;

use App\Domain\Pillars\Enums\TeamId;
use App\Domain\Pillars\Models\JetTeam;
use App\Domain\Pillars\Models\JetTeamCity;
use App\Domain\Pillars\Models\JetTeamScheduleRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class EloquentJetTeamRepository implements JetTeamRepositoryInterface
{
    public function findTeam(TeamId $team): ?JetTeam
    {
        return JetTeam::query()->where('team', $team->value)->first();
    }

    public function findByBasePath(string $basePath): ?JetTeam
    {
        $normalized = Str::start($basePath, '/');

        return JetTeam::query()->where('base_path', $normalized)->first();
    }

    public function allTeams(): Collection
    {
        // Order by the natural key so the sequence is deterministic and matches
        // the legacy [blue-angels, thunderbirds] order regardless of seed order.
        return JetTeam::query()->orderBy('team')->get();
    }

    public function schedule(TeamId $team): Collection
    {
        return JetTeamScheduleRow::query()
            ->whereRelation('team', 'team', $team->value)
            ->orderBy('sort_order')
            ->get();
    }

    public function publishedCities(TeamId $team): Collection
    {
        return JetTeamCity::query()
            ->whereRelation('team', 'team', $team->value)
            ->where('published', true)
            ->orderBy('city')
            ->get();
    }

    public function findCity(TeamId $team, string $slug): ?JetTeamCity
    {
        return JetTeamCity::query()
            ->whereRelation('team', 'team', $team->value)
            ->where('slug', $slug)
            ->first();
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Research\Repositories;

use App\Domain\Research\Models\Skill;
use Illuminate\Database\Eloquent\Collection;

final class EloquentSkillRepository implements SkillRepositoryInterface
{
    public function all(): Collection
    {
        return Skill::query()->orderBy('key')->get();
    }
}

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

    public function recordContentHash(Skill $skill, string $hash, bool $bumpVersion): Skill
    {
        $locked = Skill::query()->whereKey($skill->getKey())->lockForUpdate()->firstOrFail();

        $locked->content_hash = $hash;
        if ($bumpVersion) {
            // Versions are monotonic integer strings (default '1'); read from the
            // locked row so a concurrent bump can't be lost.
            $locked->current_version = (string) ((int) $locked->current_version + 1);
        }
        $locked->save();

        return $locked;
    }
}

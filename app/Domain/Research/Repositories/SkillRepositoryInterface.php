<?php

declare(strict_types=1);

namespace App\Domain\Research\Repositories;

use App\Domain\Research\Models\Skill;
use Illuminate\Database\Eloquent\Collection;

/**
 * Data access for the Skill provenance registry. Callers depend on this interface;
 * the Eloquent implementation is bound in DomainServiceProvider.
 */
interface SkillRepositoryInterface
{
    /**
     * Every registered skill, ordered by `key`.
     *
     * @return Collection<int, Skill>
     */
    public function all(): Collection;

    /**
     * Lock the skill's row, store `$hash` as its `content_hash`, and — when
     * `$bumpVersion` is true (a real content change, not the first-time baseline) —
     * increment `current_version`. Locking serializes concurrent detectors so a
     * single change can't double-bump. Must run inside the caller's transaction;
     * returns the fresh model.
     */
    public function recordContentHash(Skill $skill, string $hash, bool $bumpVersion): Skill;

    /** The skill registered under this key, or null — used to stamp its current version onto a run. */
    public function findByKey(string $key): ?Skill;
}

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
}

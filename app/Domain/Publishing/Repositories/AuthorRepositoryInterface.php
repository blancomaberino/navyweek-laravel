<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read access to the editorial `users` who have a PUBLIC author profile — the byline
 * accounts that get an `/authors/{slug}/` page. Keeps `pages:generate-authors` (a domain
 * action) off `User::query()` directly, per the repository-access rule.
 */
interface AuthorRepositoryInterface
{
    /**
     * Every user with a non-empty public profile `slug` (the accounts an author page is
     * generated for), ordered by name for a deterministic, diff-stable sweep.
     *
     * @return Collection<int, User>
     */
    public function publicProfiles(): Collection;
}

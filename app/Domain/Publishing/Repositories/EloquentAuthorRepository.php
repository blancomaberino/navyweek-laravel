<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class EloquentAuthorRepository implements AuthorRepositoryInterface
{
    public function publicProfiles(): Collection
    {
        return User::query()
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->orderBy('name')
            ->get();
    }
}

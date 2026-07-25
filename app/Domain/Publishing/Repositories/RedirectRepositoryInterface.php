<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Repositories;

use App\Domain\Publishing\Models\Redirect;

interface RedirectRepositoryInterface
{
    /**
     * The active redirect that applies to a normalized request path: an exact
     * match wins over any prefix match; among prefix matches the longest
     * `from_path` wins (most specific rule). Null when nothing matches.
     */
    public function matchFor(string $path): ?Redirect;

    public function incrementHits(Redirect $redirect): void;
}

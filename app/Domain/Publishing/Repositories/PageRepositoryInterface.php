<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Repositories;

interface PageRepositoryInterface
{
    /**
     * Whether a published page owns this exact canonical `url_path`. The DB
     * successor to the legacy build-time route manifest (`VALID_ROUTES`): a hit
     * means the request is a live route and passes through; a miss falls to the
     * catch-all → "/".
     */
    public function publishedPathExists(string $urlPath): bool;
}

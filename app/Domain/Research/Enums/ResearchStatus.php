<?php

declare(strict_types=1);

namespace App\Domain\Research\Enums;

/**
 * Lifecycle status of a research brief. Re-running research supersedes the prior
 * row; time- or skill-based staleness flips a `complete` brief to `stale`.
 */
enum ResearchStatus: string
{
    case Draft = 'draft';
    case Complete = 'complete';
    case Stale = 'stale';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Complete => 'Complete',
            self::Stale => 'Stale',
            self::Superseded => 'Superseded',
        };
    }
}

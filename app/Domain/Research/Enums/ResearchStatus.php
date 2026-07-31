<?php

declare(strict_types=1);

namespace App\Domain\Research\Enums;

use App\Domain\Shared\Enums\HasLabel;

/**
 * Lifecycle status of a research brief. Re-running research supersedes the prior
 * row; time- or skill-based staleness flips a `complete` brief to `stale`.
 */
enum ResearchStatus: string implements HasLabel
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

    /**
     * Badge color for admin surfaces — the single source so every table/relation
     * manager that renders this status stays visually consistent.
     */
    public function color(): string
    {
        return match ($this) {
            self::Complete => 'success',
            self::Draft => 'gray',
            self::Stale => 'warning',
            self::Superseded => 'danger',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Enums;

/**
 * Lifecycle of a Navy Week stop. Ported verbatim from the legacy `NavyWeekEvent`
 * status union.
 */
enum NavyWeekStatus: string
{
    case Completed = 'completed';
    case Active = 'active';
    case Upcoming = 'upcoming';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Completed',
            self::Active => 'Active',
            self::Upcoming => 'Upcoming',
        };
    }
}

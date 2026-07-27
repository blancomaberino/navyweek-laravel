<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Enums;

/**
 * Status pill for a Fleet Week (Block 1), wired to schema.org `eventStatus`.
 * Ported verbatim from the legacy `FleetWeekStatus` union:
 *   confirmed/scheduled → EventScheduled · cancelled → EventCancelled ·
 *   rescheduled → EventRescheduled · off-season → next-cycle EventScheduled ·
 *   none → Tier-3 city with no official fleet week (no Event schema emitted).
 */
enum FleetWeekStatus: string
{
    case Confirmed = 'confirmed';
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
    case Rescheduled = 'rescheduled';
    case OffSeason = 'off-season';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmed',
            self::Scheduled => 'Scheduled',
            self::Cancelled => 'Cancelled',
            self::Rescheduled => 'Rescheduled',
            self::OffSeason => 'Off-season',
            self::None => 'No official fleet week',
        };
    }

    /** Whether this city has an official, dated fleet week (i.e. not Tier-3). */
    public function hasOfficialEvent(): bool
    {
        return $this !== self::None;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Enums;

/**
 * Lifecycle of a single jet-team stop (port of the legacy `JetTeamStatus` union),
 * mapped onto schema.org Event status: scheduled → EventScheduled, cancelled →
 * EventCancelled, postponed → EventPostponed. `completed` (a past stop) has no
 * schema.org token, so it keeps EventScheduled with its now-past dates per Google
 * guidance — the record is archived in place. Defaults to `scheduled`.
 */
enum JetTeamStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
    case Postponed = 'postponed';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Cancelled => 'Cancelled',
            self::Postponed => 'Postponed',
            self::Completed => 'Completed',
        };
    }
}

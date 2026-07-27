<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Enums;

/**
 * Lifecycle of an air show event (port of the legacy `AirShowStatus` union),
 * mapped 1:1 onto schema.org Event status when the guide emits Event JSON-LD.
 */
enum AirShowStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
    case Postponed = 'postponed';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Cancelled => 'Cancelled',
            self::Postponed => 'Postponed',
        };
    }
}

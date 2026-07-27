<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Enums;

/**
 * Whether an air-show / jet-team event is free or ticketed (port of the legacy
 * `'FREE' | 'TICKETED'` union). Shared by the AirShow and JetTeam aggregates.
 */
enum Admission: string
{
    case Free = 'FREE';
    case Ticketed = 'TICKETED';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Ticketed => 'Ticketed',
        };
    }
}

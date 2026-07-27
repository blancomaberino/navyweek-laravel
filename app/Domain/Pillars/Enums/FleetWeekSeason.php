<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Enums;

/**
 * Season bucket for a Fleet Week — powers hub grouping and same-season
 * cross-linking. Ported verbatim from the legacy `FleetWeekSeason` union.
 */
enum FleetWeekSeason: string
{
    case Spring = 'spring';
    case Summer = 'summer';
    case Fall = 'fall';
    case Winter = 'winter';

    public function label(): string
    {
        return match ($this) {
            self::Spring => 'Spring',
            self::Summer => 'Summer',
            self::Fall => 'Fall',
            self::Winter => 'Winter',
        };
    }
}

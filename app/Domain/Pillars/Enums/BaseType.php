<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Enums;

/**
 * Installation type of a naval Base — the hub discriminator (bases are grouped by
 * type, e.g. all Naval Air Stations). Ported verbatim from the legacy `BaseType`
 * union; backing values match the source strings so the import is a straight map.
 */
enum BaseType: string
{
    case NavalStation = 'Naval Station';
    case NavalBase = 'Naval Base';
    case NavalAirStation = 'NAS';
    case SubmarineBase = 'SUBASE';
    case JointBase = 'Joint Base';
    case NavalSupportActivity = 'Naval Support Activity';
    case Other = 'Other';

    /** Full singular label (was `BASE_TYPE_LABELS`). */
    public function label(): string
    {
        return match ($this) {
            self::NavalStation => 'Naval Station',
            self::NavalBase => 'Naval Base',
            self::NavalAirStation => 'Naval Air Station',
            self::SubmarineBase => 'Submarine Base',
            self::JointBase => 'Joint Base',
            self::NavalSupportActivity => 'Naval Support Activity',
            self::Other => 'Other Installation',
        };
    }

    /** Plural label for hub headings (was `BASE_TYPE_PLURAL`). */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::NavalStation => 'Naval Stations',
            self::NavalBase => 'Naval Bases',
            self::NavalAirStation => 'Naval Air Stations',
            self::SubmarineBase => 'Submarine Bases',
            self::JointBase => 'Joint Bases',
            self::NavalSupportActivity => 'Naval Support Activities',
            self::Other => 'Other Installations',
        };
    }
}

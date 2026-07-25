<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Enums;

/**
 * Officer-designator community grouping (was the legacy `DesignatorCommunity`
 * union). Casts `ranks.designator_community` for `officer-designator` entries.
 */
enum DesignatorCommunity: string
{
    case UnrestrictedLine = 'url';
    case RestrictedLine = 'restricted-line';
    case StaffCorps = 'staff-corps';

    /** Full label (was `DESIGNATOR_COMMUNITY_LABELS`). */
    public function label(): string
    {
        return match ($this) {
            self::UnrestrictedLine => 'Unrestricted Line',
            self::RestrictedLine => 'Restricted Line',
            self::StaffCorps => 'Staff Corps',
        };
    }

    /** Short label (was `DESIGNATOR_COMMUNITY_SHORT`). */
    public function shortLabel(): string
    {
        return match ($this) {
            self::UnrestrictedLine => 'URL',
            self::RestrictedLine => 'RL',
            self::StaffCorps => 'Staff',
        };
    }
}

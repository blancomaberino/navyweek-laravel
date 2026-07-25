<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Enums;

/**
 * The U.S. geographic combatant command an overseas installation / host country
 * falls under (was the legacy `Region` union). Groups OCONUS bases on the
 * country/region hubs.
 */
enum CombatantCommand: string
{
    case Pacom = 'PACOM';
    case Eucom = 'EUCOM';
    case Centcom = 'CENTCOM';
    case Africom = 'AFRICOM';
    case Southcom = 'SOUTHCOM';

    /** Full label (was `REGION_LABELS`). */
    public function label(): string
    {
        return match ($this) {
            self::Pacom => 'U.S. Indo-Pacific Command (PACOM)',
            self::Eucom => 'U.S. European Command (EUCOM)',
            self::Centcom => 'U.S. Central Command (CENTCOM)',
            self::Africom => 'U.S. Africa Command (AFRICOM)',
            self::Southcom => 'U.S. Southern Command (SOUTHCOM)',
        };
    }
}

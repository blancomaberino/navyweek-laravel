<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

/**
 * Who the primary source says qualifies for a Veterans Day free-meal offer.
 * Ported verbatim from the legacy `Eligibility` union. A meal's eligibility is a
 * set of these, cast on the model as an `AsEnumCollection`. Only flags the source
 * explicitly supports are recorded — nothing is inferred.
 */
enum MealEligibility: string
{
    case Veteran = 'veteran';
    case Active = 'active';
    case Reserve = 'reserve';
    case Guard = 'guard';
    case Retired = 'retired';

    /** Human label for the table + filters (matches the legacy ELIGIBILITY_LABELS). */
    public function label(): string
    {
        return match ($this) {
            self::Veteran => 'Veterans',
            self::Active => 'Active duty',
            self::Reserve => 'Reserve',
            self::Guard => 'National Guard',
            self::Retired => 'Retirees',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

/**
 * Lifecycle gate for a Veterans Day meal offer. Ported verbatim from the legacy
 * `MealStatus` union. Only `Verified` entries render on the page (and only when
 * they also carry a primary `source_url`); lapsed offers flip to `Discontinued`
 * and stop rendering rather than being deleted, preserving the audit trail.
 */
enum MealStatus: string
{
    case Verified = 'verified';
    case Pending = 'pending';
    case Discontinued = 'discontinued';

    public function label(): string
    {
        return match ($this) {
            self::Verified => 'Verified',
            self::Pending => 'Pending',
            self::Discontinued => 'Discontinued',
        };
    }
}

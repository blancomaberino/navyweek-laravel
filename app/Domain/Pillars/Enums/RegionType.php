<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Enums;

/**
 * Whether a Base is located in a U.S. state, a foreign country, or a U.S.
 * territory — the discriminator that decides which columns apply (state fields vs
 * overseas fields) and whether the base is OCONUS. Defaults to `state` for legacy
 * CONUS bases that predate the field.
 */
enum RegionType: string
{
    case State = 'state';
    case Country = 'country';
    case Territory = 'territory';

    /** OCONUS = a foreign country or a U.S. territory (was `isOverseas()`). */
    public function isOverseas(): bool
    {
        return $this === self::Country || $this === self::Territory;
    }
}

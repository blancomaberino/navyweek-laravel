<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Enums;

/**
 * The kind of rank entry — the single-table-inheritance discriminator on `ranks`.
 * Decides which variant columns apply and which list page the entry renders on
 * (officer/enlisted → /navy-ranks/, ratings → /navy-ratings/, designators →
 * /navy-designators/<slug>/). Ported verbatim from the legacy `RankCategory` union.
 */
enum RankCategory: string
{
    case OfficerCommissioned = 'officer-commissioned';
    case OfficerWarrant = 'officer-warrant';
    case EnlistedPaygrade = 'enlisted-paygrade';
    case OfficerDesignator = 'officer-designator';
    case RatingActive = 'rating-active';
    case RatingHistorical = 'rating-historical';

    /** Label (was `RANK_CATEGORY_LABELS`). */
    public function label(): string
    {
        return match ($this) {
            self::OfficerCommissioned => 'Commissioned Officer',
            self::OfficerWarrant => 'Warrant Officer',
            self::EnlistedPaygrade => 'Enlisted Paygrade',
            self::OfficerDesignator => 'Officer Designator',
            self::RatingActive => 'Active Rating',
            self::RatingHistorical => 'Historical Rating',
        };
    }

    /** True for the two rating categories (render on /navy-ratings/). */
    public function isRating(): bool
    {
        return $this === self::RatingActive || $this === self::RatingHistorical;
    }
}

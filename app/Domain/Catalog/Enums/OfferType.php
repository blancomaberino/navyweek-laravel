<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

use App\Domain\Shared\Enums\HasLabel;

/**
 * The kind of offer a Connection carries. A connection can hold several — an
 * everyday discount, a stacked promo, a membership perk, or an advisory note
 * documenting that the brand has no first-party military discount.
 */
enum OfferType: string implements HasLabel
{
    case Everyday = 'everyday';
    case Promo = 'promo';
    case AdvisoryNoDiscount = 'advisory_no_discount';
    case Membership = 'membership';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Everyday => 'Everyday discount',
            self::Promo => 'Promo',
            self::AdvisoryNoDiscount => 'Advisory (no discount)',
            self::Membership => 'Membership',
            self::Other => 'Other',
        };
    }
}

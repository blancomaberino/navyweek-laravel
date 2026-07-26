<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

/**
 * How a Veterans Day free-meal offer is redeemed, per the source. Ported verbatim
 * from the legacy `Redemption` union.
 */
enum MealRedemption: string
{
    case DineIn = 'dine-in';
    case Takeout = 'takeout';
    case Both = 'both';

    /** Human label (matches the legacy REDEMPTION_LABELS). */
    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Dine-in',
            self::Takeout => 'Takeout',
            self::Both => 'Dine-in or takeout',
        };
    }
}

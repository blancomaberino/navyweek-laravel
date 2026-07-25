<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Enums;

/**
 * Enlisted-rating community grouping (was the legacy `RatingCommunity` union).
 * Casts `ranks.rating_community` for `rating-active` / `rating-historical` entries.
 */
enum RatingCommunity: string
{
    case General = 'general';
    case Aviation = 'aviation';
    case Submarine = 'submarine';
    case Nuclear = 'nuclear';
    case Seabees = 'seabees';
    case Medical = 'medical';
    case Cryptologic = 'cryptologic';
    case SpecialWarfare = 'special-warfare';
    case Admin = 'admin';

    /** Full label (was `RATING_COMMUNITY_LABELS`). */
    public function label(): string
    {
        return match ($this) {
            self::General => 'General Surface & Combat',
            self::Aviation => 'Naval Aviation',
            self::Submarine => 'Submarine Force',
            self::Nuclear => 'Nuclear Power',
            self::Seabees => 'Seabees (NCF)',
            self::Medical => 'Medical & Dental',
            self::Cryptologic => 'Cryptologic & IW',
            self::SpecialWarfare => 'Special Warfare & EOD',
            self::Admin => 'Admin & Logistics',
        };
    }

    /** Short label (was `RATING_COMMUNITY_SHORT`). */
    public function shortLabel(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Aviation => 'Aviation',
            self::Submarine => 'Submarine',
            self::Nuclear => 'Nuclear',
            self::Seabees => 'Seabees',
            self::Medical => 'Medical',
            self::Cryptologic => 'Cryptologic',
            self::SpecialWarfare => 'Spec War',
            self::Admin => 'Admin',
        };
    }
}

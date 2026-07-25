<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

use App\Domain\Shared\Models\Source;

/**
 * The kind of citation a {@see Source} is — how directly
 * it substantiates a claim. A closed vocabulary (cast on `sources.source_type`),
 * matching the enum-per-vocabulary convention used across the domain.
 */
enum SourceType: string
{
    /** The brand's / authority's own page — the strongest evidence (YMYL). */
    case Primary = 'primary';
    /** Reputable third-party reporting of a primary fact. */
    case Secondary = 'secondary';
    /** An official program/partner page (ID.me, SheerID, GovX, …). */
    case Official = 'official';
    /** Press release or news coverage. */
    case Press = 'press';
    /** Community/user-reported (weakest — corroborate before asserting). */
    case Community = 'community';

    public function label(): string
    {
        return match ($this) {
            self::Primary => 'Primary',
            self::Secondary => 'Secondary',
            self::Official => 'Official',
            self::Press => 'Press',
            self::Community => 'Community',
        };
    }
}

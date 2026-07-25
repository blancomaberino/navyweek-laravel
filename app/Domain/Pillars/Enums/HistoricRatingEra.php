<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Enums;

/**
 * Era tag for a decommissioned (historical) rating (was the legacy
 * `HistoricRatingEra` union). Stored as an enum collection on `ranks.era_tags`.
 */
enum HistoricRatingEra: string
{
    case ColdWar = 'cold-war';
    case PostColdWar = 'post-cold-war';
    case Consolidation2000s = '2000s-consolidation';
    case Consolidation2010s = '2010s-consolidation';

    /** Label (was `HISTORIC_RATING_ERA_LABELS`). */
    public function label(): string
    {
        return match ($this) {
            self::ColdWar => 'Cold War (pre-1990)',
            self::PostColdWar => 'Post-Cold War (1990s)',
            self::Consolidation2000s => '2000s Consolidation',
            self::Consolidation2010s => '2010s Consolidation',
        };
    }
}

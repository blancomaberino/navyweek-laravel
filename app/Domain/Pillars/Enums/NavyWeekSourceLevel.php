<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Enums;

/**
 * Provenance tier for a Navy Week venue / schedule item (port of the legacy
 * `SourceLevel` union + `SOURCE_LEVELS` map). Drives the confidence badge on
 * schedule rows: NAVCO-confirmed, anchor-event-confirmed, or unverified local
 * context compiled by NavyWeek.org. Embedded inside the `venues`/`daily_schedule`
 * JSON on a Navy Week event; this enum carries the display label + description.
 */
enum NavyWeekSourceLevel: string
{
    case Navco = 'navco';
    case Anchor = 'anchor';
    case Local = 'local';

    public function label(): string
    {
        return match ($this) {
            self::Navco => 'NAVCO-confirmed',
            self::Anchor => 'Anchor-event-confirmed',
            self::Local => 'Local context — unverified',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Navco => 'Confirmed by the Navy Office of Community Outreach (NAVCO) official city page.',
            self::Anchor => 'Confirmed by the anchor event organizer (air show, festival, or host venue).',
            self::Local => 'Local context or expected programming compiled by NavyWeek.org — not yet confirmed by NAVCO.',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Crm\Enums;

use App\Domain\Shared\Enums\HasLabel;

/**
 * Pipeline status for a Connection (a brand in the CRM).
 *
 * Ported from the legacy `pipeline/queue/*.json` status vocabulary and the
 * reconcile-state.py rules. `needs-reverify` is the skill-upgrade / staleness
 * trigger described in the rebuild plan.
 */
enum ConnectionStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Drafted = 'drafted';
    case Published = 'published';
    case Duplicate = 'duplicate';
    case Skipped = 'skipped';
    case NeedsInfo = 'needs-info';
    case NeedsReverify = 'needs-reverify';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Drafted => 'Drafted',
            self::Published => 'Published',
            self::Duplicate => 'Duplicate',
            self::Skipped => 'Skipped',
            self::NeedsInfo => 'Needs info',
            self::NeedsReverify => 'Needs re-verify',
        };
    }

    /** Statuses that represent a live, indexable brand page. */
    public function isLive(): bool
    {
        return $this === self::Published;
    }
}

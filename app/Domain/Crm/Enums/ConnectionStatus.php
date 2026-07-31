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

    /**
     * The active pipeline states a cadence sweep may move to `needs-reverify` — the
     * single source for "an active brand" shared by the sweep command's pre-filter and
     * the repository's under-lock re-check (they must agree, or the dry-run count drifts
     * from what actually transitions).
     *
     * @return list<self>
     */
    public static function activeForReview(): array
    {
        return [self::Published, self::Drafted];
    }

    /** Filament badge color for this status — the single source for every status badge. */
    public function color(): string
    {
        return match ($this) {
            self::Published => 'success',
            self::Drafted => 'info',
            self::Pending => 'gray',
            self::Duplicate,
            self::NeedsInfo,
            self::NeedsReverify => 'warning',
            self::Skipped => 'danger',
        };
    }
}

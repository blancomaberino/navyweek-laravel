<?php

declare(strict_types=1);

namespace App\Domain\Research\Actions;

use App\Domain\Research\Enums\ResearchStatus;
use App\Domain\Research\Models\Research;
use Illuminate\Support\Facades\DB;

/**
 * Marks a research brief verified and recomputes the connection's review cadence —
 * the foundation the automation spine (FlagStaleResearch, the research job) reuses.
 *
 * Sets the brief to Complete + stamps `last_verified`, then bumps the connection's
 * `last_verified_at` and recomputes `next_review_due = last_verified_at +
 * research_cadence_days`. Per the build-clock rule it NEVER touches `pages.date_*` —
 * only `last_verified` traces to research; page dates come from the build.
 */
final class MarkResearchVerifiedAction
{
    public function __invoke(Research $research): void
    {
        DB::transaction(function () use ($research): void {
            $now = now();

            $research->last_verified = $now;
            $research->status = ResearchStatus::Complete;
            $research->save();

            $connection = $research->connection;
            $connection->last_verified_at = $now;
            $connection->next_review_due = $now->copy()->addDays($connection->research_cadence_days);
            $connection->save();
        });
    }
}

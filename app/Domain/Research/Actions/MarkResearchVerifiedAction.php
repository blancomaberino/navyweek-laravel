<?php

declare(strict_types=1);

namespace App\Domain\Research\Actions;

use App\Domain\Crm\Repositories\ConnectionRepositoryInterface;
use App\Domain\Research\Exceptions\CannotVerifyNonLatestResearchException;
use App\Domain\Research\Models\Research;
use App\Domain\Research\Repositories\ResearchRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Marks a research brief verified and recomputes the connection's review cadence —
 * the foundation the automation spine (FlagStaleResearch, the research job) reuses.
 *
 * Sets the brief to Complete + stamps `last_verified`, then bumps the connection's
 * `last_verified_at` and recomputes `next_review_due = last_verified_at +
 * research_cadence_days`. Per the build-clock rule it NEVER touches `pages.date_*` —
 * only `last_verified` traces to research; page dates come from the build.
 *
 * Only the current (highest-version) brief may be verified — verifying a superseded
 * one would stamp the cadence from stale research and leave two Complete briefs for
 * one connection. Both writes go through the repositories and run in one transaction
 * (each repo locks its row), so a concurrent edit can't tear the update.
 *
 * The parent connection is locked FIRST, before the latest-version guard reads, so two
 * concurrent verifies of the same connection serialize on it. (A brand-new version
 * inserted by a writer that doesn't take this lock — the importer or the research form —
 * is still possible but benign: it supersedes the prior brief, so the guard's stale
 * `$latest` is at worst verified a beat early and the next research run reconciles it.)
 */
final class MarkResearchVerifiedAction
{
    public function __construct(
        private readonly ResearchRepositoryInterface $research,
        private readonly ConnectionRepositoryInterface $connections,
    ) {}

    public function __invoke(Research $research): void
    {
        DB::transaction(function () use ($research): void {
            // Lock the connection before the guard read so concurrent verifies serialize.
            $connection = $this->connections->lockById($research->connection_id);
            if ($connection === null) {
                return; // connection deleted/trashed concurrently
            }

            $latest = $this->research->latestForConnection($research->connection_id);
            if ($latest === null || $latest->getKey() !== $research->getKey()) {
                throw CannotVerifyNonLatestResearchException::forConnection($research->connection_id);
            }

            $now = now();
            $this->research->markVerified($research, $now);
            $this->connections->recordVerification($connection, $now);
        });
    }
}

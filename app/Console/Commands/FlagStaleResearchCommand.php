<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Models\Connection;
use App\Domain\Crm\Repositories\ConnectionRepositoryInterface;
use App\Domain\Research\Repositories\ResearchRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Daily cadence sweep: connections past their `next_review_due` get their status
 * moved to `needs-reverify` and their latest brief marked `stale` (only when it is
 * `Complete` — a mid-flight Draft is never clobbered), so the CRM (and the "Due for
 * review" filter / dashboard) surface them. Reuses the existing
 * `ConnectionRepository::dueForReview`. Only active states (published/drafted) are
 * swept — duplicates, skipped, pending, and already-flagged brands are left alone.
 * All writes go through the repositories (each `lockForUpdate`).
 *
 * Auto-dispatching the research job for high-priority brands lands with that job
 * (it needs the queue + CLI creds); this command only flags.
 */
final class FlagStaleResearchCommand extends Command
{
    protected $signature = 'research:flag-stale {--dry-run : Report the count only, write nothing}';

    protected $description = 'Flag connections past their research cadence: latest brief → stale, connection → needs-reverify.';

    public function handle(ConnectionRepositoryInterface $connections, ResearchRepositoryInterface $research): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $due = $connections->dueForReview(now())
            ->filter(static fn (Connection $c): bool => in_array(
                $c->status,
                ConnectionStatus::activeForReview(),
                true,
            ));

        // One batched read of the latest brief per due connection (keyed by
        // connection_id) instead of a query per iteration.
        $latestByConnection = $research->latestForConnections(
            $due->map(static fn (Connection $c): int => $c->id)->all()
        );

        if ($dryRun) {
            $this->info("[dry-run] {$due->count()} connection(s) would be flagged for re-verification.");

            return self::SUCCESS;
        }

        $flagged = 0;
        $failed = 0;
        foreach ($due as $connection) {
            $latest = $latestByConnection->get($connection->id);

            // Per-connection transaction, isolated: one bad row (e.g. deleted mid-sweep)
            // is logged and skipped so it can't abort the rest of a daily, idempotent run.
            try {
                $didFlag = DB::transaction(function () use ($connection, $latest, $research, $connections): bool {
                    // markNeedsReverify re-checks under the lock that the connection is
                    // still active; if it isn't (edited to skipped/duplicate since the
                    // read), skip both writes — don't stale the brief of a brand we're
                    // no longer flagging.
                    if (! $connections->markNeedsReverify($connection)) {
                        return false;
                    }

                    // markStale is a no-op unless the brief is Complete (a Draft is
                    // mid-flight; Superseded/Stale are terminal) — never clobbers
                    // in-progress research.
                    if ($latest !== null) {
                        $research->markStale($latest);
                    }

                    return true;
                });

                if ($didFlag) {
                    $flagged++;
                }
            } catch (\Throwable $e) {
                $failed++;
                report($e);
                $this->warn("Skipped connection {$connection->id}: {$e->getMessage()}");
            }
        }

        $this->info("{$flagged} connection(s) flagged for re-verification.");
        if ($failed > 0) {
            $this->warn("{$failed} connection(s) skipped due to errors (see logs).");
        }

        return self::SUCCESS;
    }
}

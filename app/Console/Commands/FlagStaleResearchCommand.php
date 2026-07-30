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
                [ConnectionStatus::Published, ConnectionStatus::Drafted],
                true,
            ));

        // One batched read of the latest brief per due connection (keyed by
        // connection_id) instead of a query per iteration.
        $latestByConnection = $research->latestForConnections(
            $due->map(static fn (Connection $c): int => $c->id)->all()
        );

        $flagged = 0;
        foreach ($due as $connection) {
            if (! $dryRun) {
                $latest = $latestByConnection->get($connection->id);
                DB::transaction(function () use ($connection, $latest, $research, $connections): void {
                    // markStale is a no-op unless the brief is Complete (a Draft is
                    // mid-flight; Superseded/Stale are terminal) — the sweep never
                    // clobbers in-progress research.
                    if ($latest !== null) {
                        $research->markStale($latest);
                    }
                    $connections->markNeedsReverify($connection);
                });
            }
            $flagged++;
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}{$flagged} connection(s) flagged for re-verification.");

        return self::SUCCESS;
    }
}

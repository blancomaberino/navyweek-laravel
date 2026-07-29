<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Models\Connection;
use App\Domain\Crm\Repositories\ConnectionRepositoryInterface;
use App\Domain\Research\Enums\ResearchStatus;
use App\Domain\Research\Repositories\ResearchRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Daily cadence sweep: connections past their `next_review_due` get their latest
 * brief marked `stale` and their status moved to `needs-reverify`, so the CRM (and
 * the "Due for review" filter / dashboard) surface them. Reuses the existing
 * `ConnectionRepository::dueForReview`. Only active states (published/drafted) are
 * swept — duplicates, skipped, pending, and already-flagged brands are left alone.
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

        $flagged = 0;
        foreach ($due as $connection) {
            if (! $dryRun) {
                DB::transaction(function () use ($connection, $research): void {
                    $latest = $research->latestForConnection($connection->id);
                    if ($latest !== null
                        && ! in_array($latest->status, [ResearchStatus::Stale, ResearchStatus::Superseded], true)) {
                        $latest->status = ResearchStatus::Stale;
                        $latest->save();
                    }

                    $connection->status = ConnectionStatus::NeedsReverify;
                    $connection->save();
                });
            }
            $flagged++;
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}{$flagged} connection(s) flagged for re-verification.");

        return self::SUCCESS;
    }
}

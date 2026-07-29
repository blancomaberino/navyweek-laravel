<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Crm\Models\Connection;
use App\Domain\Crm\Repositories\ConnectionRepositoryInterface;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Research\Repositories\ResearchRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Reports pipeline-state drift between a connection's `status` and the facts the DB
 * already proves — the Laravel successor to the legacy `reconcile-state.py` gate.
 * READ-ONLY: it never writes; `--check` exits non-zero on drift so CI/scheduling can
 * gate on it. Correcting a status is a human/admin decision, surfaced here.
 */
final class ReconcileConnectionsCommand extends Command
{
    protected $signature = 'connections:reconcile {--check : Exit non-zero when drift is found (report only, never writes)}';

    protected $description = 'Report pipeline-state drift: live pages without research (YMYL) and status that disagrees with the DB facts.';

    public function handle(
        PageRepositoryInterface $pages,
        ResearchRepositoryInterface $research,
        ConnectionRepositoryInterface $connections,
    ): int {
        $publishedIds = $pages->connectionIdsWithPublishedDiscountBrandPage();
        $researchedIds = $research->connectionIdsWithBriefs();

        /** @var array<int, array{0: string, 1: Collection<int, Connection>}> $sections */
        $sections = [
            // YMYL: a live page with no research brief behind it (the R6 invariant).
            ['YMYL — published page, no research brief', $connections->publishedPagesMissingResearch($publishedIds, $researchedIds)],
            // A live page whose connection isn't marked published.
            ['Status drift — live page not marked published', $connections->liveNotMarkedPublished($publishedIds)],
            // A duplicate (duplicate_of set) not marked as such.
            ['Status drift — duplicate not marked duplicate', $connections->duplicatesNotMarkedDuplicate()],
        ];

        $total = 0;
        foreach ($sections as [$label, $rows]) {
            $count = $rows->count();
            $total += $count;

            if ($count === 0) {
                $this->info("✓ {$label}: none");

                continue;
            }

            $sample = $rows->take(10)->pluck('slug')->implode(', ');
            $this->warn("✗ {$label}: {$count}");
            $this->line("    e.g. {$sample}");
        }

        if ($total === 0) {
            $this->info('Pipeline state is consistent.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn("{$total} drift issue(s) found.");

        return $this->option('check') ? self::FAILURE : self::SUCCESS;
    }
}

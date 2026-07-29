<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Models\Connection;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Research\Models\Research;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

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

    public function handle(): int
    {
        // Connections that own a published discount-brand page (a "live" brand).
        $publishedConnectionIds = Offer::query()
            ->whereIn('id', Page::query()
                ->where('page_type', PageType::DiscountBrand)
                ->where('is_published', true)
                ->where('pageable_type', (new Offer)->getMorphClass())
                ->select('pageable_id'))
            ->pluck('connection_id');

        $researchedConnectionIds = Research::query()->distinct()->pluck('connection_id');

        /** @var array<int, array{0: string, 1: Builder<Connection>}> $sections */
        $sections = [
            // YMYL: a live page with no research brief behind it (the R6 invariant).
            ['YMYL — published page, no research brief', Connection::query()
                ->whereIn('id', $publishedConnectionIds)
                ->whereNotIn('id', $researchedConnectionIds)
                ->orderBy('slug')],
            // A live page whose connection isn't marked published.
            ['Status drift — live page not marked published', Connection::query()
                ->whereIn('id', $publishedConnectionIds)
                ->whereNull('duplicate_of')
                ->where('status', '!=', ConnectionStatus::Published->value)
                ->orderBy('slug')],
            // A duplicate (duplicate_of set) not marked as such.
            ['Status drift — duplicate not marked duplicate', Connection::query()
                ->whereNotNull('duplicate_of')
                ->where('status', '!=', ConnectionStatus::Duplicate->value)
                ->orderBy('slug')],
        ];

        $total = 0;
        foreach ($sections as [$label, $query]) {
            $count = (clone $query)->count();
            $total += $count;

            if ($count === 0) {
                $this->info("✓ {$label}: none");

                continue;
            }

            $sample = (clone $query)->limit(10)->pluck('slug')->implode(', ');
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

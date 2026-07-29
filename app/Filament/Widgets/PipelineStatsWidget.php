<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Repositories\ConnectionRepositoryInterface;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * CRM dashboard overview — the pipeline at a glance across the ~15.3k connection
 * universe: total, published (live pages), how many are due for re-verification
 * today (the research-cadence backlog), and how many are still in the queue backlog.
 */
class PipelineStatsWidget extends StatsOverviewWidget
{
    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $connections = app(ConnectionRepositoryInterface::class);

        return [
            Stat::make('Connections', (string) $connections->total())
                ->description('Brands in the universe'),
            Stat::make('Published', (string) $connections->countByStatus(ConnectionStatus::Published))
                ->description('Live pages'),
            Stat::make('Due for review', (string) $connections->dueForReviewCount(now()))
                ->description('Past the research cadence')
                ->color('warning'),
            Stat::make('Backlog', (string) $connections->backlogCount())
                ->description('Not yet promoted'),
        ];
    }
}
